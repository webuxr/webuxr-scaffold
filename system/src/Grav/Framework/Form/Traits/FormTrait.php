<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form\Traits;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Data\ValidationException;
use Grav\Common\Form\FormFlash;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Session\Session;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Trait FormTrait
 * @package Grav\Framework\Form
 */
trait FormTrait
{
    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var string */
    private $uniqueid;
    /** @var bool */
    private $submitted;
    /** @var string[] */
    private $errors;
    /** @var \ArrayAccess */
    private $data;
    /** @var array|UploadedFileInterface[] */
    private $files;
    /** @var FormFlash */
    private $flash;
    /** @var Blueprint */
    private $blueprint;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueid;
    }

    public function setUniqueId(string $uniqueId): void
    {
        $this->uniqueid = $uniqueId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFormName(): string
    {
        return $this->name;
    }

    public function getNonceName(): string
    {
        return 'form-nonce';
    }

    public function getNonceAction(): string
    {
        return 'form';
    }

    public function getNonce(): string
    {
        return Utils::getNonce($this->getNonceAction());
    }

    public function getAction(): string
    {
        return '';
    }

    public function getData(string $name = null)
    {
        return null !== $name ? $this->data[$name] : $this->data;
    }

    /**
     * @return array|UploadedFileInterface[]
     */
    public function getFiles(): array
    {
        return $this->files ?? [];
    }

    public function getValue(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function handleRequest(ServerRequestInterface $request): FormInterface
    {
        try {
            [$data, $files] = $this->parseRequest($request);

            $this->submit($data, $files);
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function setRequest(ServerRequestInterface $request): FormInterface
    {
        [$data, $files] = $this->parseRequest($request);

        $this->data = new Data($data, $this->getBlueprint());
        $this->files = $files;

        return $this;
    }

    public function isValid(): bool
    {
        return !$this->errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function validate(): bool
    {
        if ($this->errors) {
            return false;
        }

        try {
            $this->validateData($this->data);
            $this->validateUploads($this->getFiles());
        } catch (ValidationException $e) {
            $list = [];
            foreach ($e->getMessages() as $field => $errors) {
                $list[] = $errors;
            }
            $list = array_merge(...$list);
            $this->errors = $list;
        }  catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        $this->filterData($this->data);

        return empty($this->errors);
    }

    /**
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return $this
     */
    public function submit(array $data, array $files = null): FormInterface
    {
        try {
            if ($this->isSubmitted()) {
                throw new \RuntimeException('Form has already been submitted');
            }

            $this->data = new Data($data, $this->getBlueprint());
            $this->files = $files ?? [];

            if (!$this->validate()) {
                return $this;
            }

            $this->doSubmit($this->data->toArray(), $this->files);

            $this->submitted = true;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this;
    }

    public function reset(): void
    {
        // Make sure that the flash object gets deleted.
        $this->getFlash()->delete();

        $this->data = null;
        $this->files = [];
        $this->errors = [];
        $this->submitted = false;
        $this->flash = null;
    }

    public function getFields(): array
    {
        return $this->getBlueprint()->fields();
    }

    public function getButtons(): array
    {
        return $this->getBlueprint()['form']['buttons'] ?? [];
    }

    public function getTasks(): array
    {
        return $this->getBlueprint()['form']['tasks'] ?? [];
    }

    abstract public function getBlueprint(): Blueprint;

    /**
     * Implements \Serializable::serialize().
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->doSerialize());
    }

    /**
     * Implements \Serializable::unserialize().
     *
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);

        $this->doUnserialize($data);
    }

    /**

     * Get form flash object.
     *
     * @return FormFlash
     */
    public function getFlash(): FormFlash
    {
        if (null === $this->flash) {
            $grav = Grav::instance();
            $user = $grav['user'];
            $id = null;

            $rememberState = $this->getBlueprint()->get('form/remember_state');
            if ($rememberState === 'user') {
                $id = $user->username;
            }

            // By default store flash by the session id.
            if (null === $id) {
                /** @var Session $session */
                $session = $grav['session'];
                $id = $session->getId();
            }

            $this->flash = new FormFlash($id, $this->getUniqueId(), $this->getName());
            $this->flash->setUrl($grav['uri']->url)->setUser($user);
        }

        return $this->flash;
    }

    protected function unsetFlash(): void
    {
        $this->flash = null;
    }

    /**
     * Set all errors.
     *
     * @param array $errors
     */
    protected function setErrors(array $errors): void
    {
        $this->errors = array_merge($this->errors, $errors);
    }

    /**
     * Set a single error.
     *
     * @param string $error
     */
    protected function setError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Parse PSR-7 ServerRequest into data and files.
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function parseRequest(ServerRequestInterface $request): array
    {
        $method = $request->getMethod();
        if (!\in_array($method, ['PUT', 'POST', 'PATCH'])) {
            throw new \RuntimeException(sprintf('FlexForm: Bad HTTP method %s', $method));
        }

        $body = $request->getParsedBody();
        $data = isset($body['data']) ? $this->decodeData($body['data']) : null;

        $flash = $this->getFlash();
        /*
        if (null !== $data) {
            $flash->setData($data);
            $flash->save();
        }
        */

        $blueprint = $this->getBlueprint();
        $includeOriginal = (bool)($blueprint->form()['images']['original'] ?? null);
        $files = $flash->getFilesByFields($includeOriginal);

        $data = $blueprint->processForm($data ?? [], $body['toggleable_data'] ?? []);

        return [
            $data,
            $files ?? []
        ];
    }

    /**
     * Form submit logic goes here.
     *
     * @param array $data
     * @param array $files
     * @return mixed
     */
    abstract protected function doSubmit(array $data, array $files);

    /**
     * Validate data and throw validation exceptions if validation fails.
     *
     * @param \ArrayAccess $data
     * @throws ValidationException
     * @throws \Exception
     */
    protected function validateData(\ArrayAccess $data): void
    {
        if ($data instanceof Data) {
            $data->validate();
        }
    }

    /**
     * Filter validated data.
     *
     * @param \ArrayAccess $data
     */
    protected function filterData(\ArrayAccess $data): void
    {
        if ($data instanceof Data) {
            $data->filter();
        }
    }

    /**
     * Validate all uploaded files.
     *
     * @param array $files
     */
    protected function validateUploads(array $files): void
    {
        foreach ($files as $file) {
            if (null === $file) {
                continue;
            }
            if ($file instanceof UploadedFileInterface) {
                $this->validateUpload($file);
            } else {
                $this->validateUploads($file);
            }
        }
    }

    /**
     * Validate uploaded file.
     *
     * @param UploadedFileInterface $file
     */
    protected function validateUpload(UploadedFileInterface $file): void
    {
        // Handle bad filenames.
        $filename = $file->getClientFilename();

        if (!Utils::checkFilename($filename)) {
            $grav = Grav::instance();
            throw new \RuntimeException(
                sprintf($grav['language']->translate('PLUGIN_FORM.FILEUPLOAD_UNABLE_TO_UPLOAD', null, true), $filename, 'Bad filename')
            );
        }
    }

    /**
     * Decode POST data
     *
     * @param array $data
     * @return array
     */
    protected function decodeData($data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        // Decode JSON encoded fields and merge them to data.
        if (isset($data['_json'])) {
            $data = array_replace_recursive($data, $this->jsonDecode($data['_json']));
            unset($data['_json']);
        }

        return $data;
    }

    /**
     * Recursively JSON decode POST data.
     *
     * @param  array $data
     * @return array
     */
    protected function jsonDecode(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (\is_array($value)) {
                $value = $this->jsonDecode($value);
            } elseif ($value === '') {
                unset($data[$key]);
            } else {
                $value = json_decode($value, true);
                if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
                    unset($data[$key]);
                    $this->errors[] = "Badly encoded JSON data (for {$key}) was sent to the form";
                }
            }
        }

        return $data;
    }

    /**
     * @return string
     */
    protected function doSerialize(): array
    {
        $data = $this->data instanceof Data ? $this->data->toArray() : null;

        return [
            'name' => $this->name,
            'id' => $this->id,
            'uniqueid' => $this->uniqueid,
            'submitted' => $this->submitted,
            'errors' => $this->errors,
            'data' => $data,
            'files' => $this->files,
        ];
    }

    /**
     * @param array $data
     */
    protected function doUnserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->id = $data['id'];
        $this->uniqueid = $data['uniqueid'];
        $this->submitted = $data['submitted'] ?? false;
        $this->errors = $data['errors'] ?? [];
        $this->data = isset($data['data']) ? new Data($data['data'], $this->getBlueprint()) : null;
        $this->files = $data['files'] ?? [];
    }
}
