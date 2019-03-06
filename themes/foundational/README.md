# Foundational

The **Foundational** Theme is for [Grav CMS](http://github.com/getgrav/grav).
The theme is built with [Zurb Foundation 6.5](https://foundation.zurb.com/sites/docs/index.html). 

You must have Node Package Manager (npm) installed before you can use this theme. Additionally, you may need to install some dependencies such as node-sass and gulp. Nodejs instructions are available all over the internet.

After copying this theme to the Grav theme folder, you must install Foundation by running NPM. 

* On the command line, navigate to the user/themes/foundational directory within your Grav installation and run the command 'npm install'.
* Open gulpfile.js and look for this code:

    function serve() {
      browserSync.init({
        proxy: "grav.devel"
      });
      
* Replace "grav.devel" with the domain name of your Grav installation 

The theme uses SCSS. Make changes to style sheets by editing the SCSS files in the SCSS directory. 

To automatically compile SCSS to CSS so that the theme can use it, on the command line run the command 'npm start'.

## Description

A Zurb Foundation 6 starter theme for Grav CMS. You can install it and edit it to make it your own, or you can use theme inheritance to create a child theme by following these instructions: https://learn.getgrav.org/themes/customization#theme-inheritance

Theme inheritance is recommended, so that updates to Foundational don't overwrite your theme customization.
