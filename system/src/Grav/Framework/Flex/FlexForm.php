<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\Traits\FormTrait;
use Grav\Framework\Route\Route;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
class FlexForm implements FlexFormInterface
{
    use FormTrait {
        FormTrait::doSerialize as doTraitSerialize;
        FormTrait::doUnserialize as doTraitUnserialize;
    }

    /** @var array|null */
    private $form;

    /** @var FlexObjectInterface */
    private $object;

    /**
     * FlexForm constructor.
     * @param string $name
     * @param FlexObjectInterface $object
     * @param array|null $form
     */
    public function __construct(string $name, FlexObjectInterface $object, array $form = null)
    {
        $this->name = $name;
        $this->form = $form;
        $this->setObject($object);
        $this->setId($this->getName());
        $this->setUniqueId(md5($this->getObject()->getStorageKey()));
        $this->errors = [];
        $this->submitted = false;

        $flash = $this->getFlash();
        if ($flash->exists()) {
            $data = $flash->getData();
            $includeOriginal = (bool)($this->getBlueprint()->form()['images']['original'] ?? null);

            $this->data = $data ? new Data($data, $this->getBlueprint()) : null;
            $this->files = $flash->getFilesByFields($includeOriginal);
        } else {
            $this->data = null;
            $this->files = [];
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        $object = $this->getObject();
        $name = $this->name ?: 'object';

        return "flex-{$object->getType(false)}-{$name}";
    }

    /**
     * @return Data|FlexObjectInterface
     */
    public function getData(): \ArrayAccess
    {
        return $this->data ?? $this->getObject();
    }

    /**
     * Get a value from the form.
     *
     * Note: Used in form fields.
     *
     * @param string $name
     * @return mixed
     */
    public function getValue(string $name)
    {
        // Attempt to get value from the form data.
        $value = $this->data ? $this->data[$name] : null;

        // Return the form data or fall back to the object property.
        return $value ?? $this->getObject()->value($name);
    }

    /**
     * @return FlexObjectInterface
     */
    public function getObject(): FlexObjectInterface
    {
        return $this->object;
    }

    public function updateObject(): FlexObjectInterface
    {
        $data = $this->data instanceof Data ? $this->data->toArray() : [];
        $files = $this->files;

        return $this->getObject()->update($data, $files);
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint
    {
        if (null === $this->blueprint) {
            try {
                $blueprint = $this->getObject()->getBlueprint($this->name);
                if ($this->form) {
                    // We have field overrides available.
                    $blueprint->extend(['form' => $this->form], true);
                    $blueprint->init();
                }
            } catch (\RuntimeException $e) {
                if (!isset($this->form['fields'])) {
                    throw $e;
                }

                // Blueprint is not defined, but we have custom form fields available.
                $blueprint = new Blueprint(null, ['form' => $this->form]);
                $blueprint->load();
                $blueprint->setScope('object');
                $blueprint->init();
            }

            $this->blueprint = $blueprint;
        }

        return $this->blueprint;
    }

    /**
     * @return Route|null
     */
    public function getFileUploadAjaxRoute(): ?Route
    {
        $object = $this->getObject();
        if (!method_exists($object, 'route')) {
            return null;
        }

        return $object->route('/edit.json/task:media.upload');
    }

    /**
     * @param $field
     * @param $filename
     * @return Route|null
     */
    public function getFileDeleteAjaxRoute($field, $filename): ?Route
    {
        $object = $this->getObject();
        if (!method_exists($object, 'route')) {
            return null;
        }

        return $object->route('/edit.json/task:media.delete');
    }

    public function getMediaTaskRoute(): string
    {
        $grav = Grav::instance();
        /** @var Flex $flex */
        $flex = $grav['flex_objects'];

        if (method_exists($flex, 'adminRoute')) {
            return $flex->adminRoute($this->getObject()) . '.json';
        }

        return '';
    }

    public function getMediaRoute(): string
    {
        return '/' . $this->getObject()->getKey();
    }

    /**
     * Implements \Serializable::unserialize().
     *
     * @param string $data
     */
    public function unserialize($data): void
    {
        $data = unserialize($data, ['allowed_classes' => [FlexObject::class]]);

        $this->doUnserialize($data);
    }

    /**
     * Note: this method clones the object.
     *
     * @param FlexObjectInterface $object
     * @return $this
     */
    protected function setObject(FlexObjectInterface $object): self
    {
        $this->object = clone $object;

        return $this;
    }

    /**
     * @param array $data
     * @param array $files
     * @throws \Exception
     */
    protected function doSubmit(array $data, array $files)
    {
        /** @var FlexObject $object */
        $object = clone $this->getObject();
        $object->update($data, $files);
        $object->save();

        $this->setObject($object);
        $this->reset();
    }

    protected function doSerialize(): array
    {
        return $this->doTraitSerialize() + [
                'object' => $this->object,
            ];
    }

    protected function doUnserialize(array $data): void
    {
        $this->doTraitUnserialize($data);

        $this->object = $data['object'];
    }

        /**
     * Filter validated data.
     *
     * @param \ArrayAccess $data
     */
    protected function filterData(\ArrayAccess $data): void
    {
        if ($data instanceof Data) {
            $data->filter(true);
        }
    }
}
