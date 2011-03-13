========
Overview
========

This bundle eases development with the Google Closure Tools_ by providing
some useful command line tools.


Installation
------------
Checkout a copy of the code::

    git submodule add https://github.com/schmittjoh/GoogleClosureBundle.git src/JMS/GoogleClosureBundle
    
Then register the bundle with your kernel::

    // in AppKernel::registerBundles()
    $bundles = array(
        // ...
        new JMS\GoogleClosureBundle\JMSGoogleClosureBundle(),
        // ...
    );

You also need to download the plovr.jar file from:

    http://code.google.com/p/plovr/downloads/list
    
Plovr_ is a lightweight wrapper around the Google Closure Tools_ (Compiler, Library,
and Templates).


Configuration
-------------

    jms_google_closure:
        plovr:
            jar_path: %kernel.root_dir%/vendor/plovr/plovr.jar
            

Usage
-----

Running plovr during development
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Plovr by default listens on port 9810, and will automatically compile your
application when a request comes in. To start plovr, you first have to create
a plovr configuration_ file. Plovr configuration files are simple Javascript
object literals. It's important that you place this file somewhere below
``Resources/cofig/plovr/`` in your bundle::

    // Resources/config/plovr/compile.js
    {
        "id": "some-unique-id",
        "paths": ["@MyBundle/Resources/public/javascript/"],
        "mode": "ADVANCED",
        "level": "VERBOSE",
        // this is the entry point to your application
        "inputs": "@MyBundle/Resources/public/javascript/app.js",
        
        "pretty-print": true,
        "debug": true
    }
    
After you have set-up the configuration file, you can run Plovr by running:

    php app/console plovr:start @MyBundle/compile.js
    
Your compiled Javascript will now be compiled upon request, and is available at:

    http://localhost:9810/compile?id=some-unique-id


Compiling for production
~~~~~~~~~~~~~~~~~~~~~~~~

When you go to production, you only want to compile the Javascript once, and probably
want the Google Closure Compiler to be a bit more aggressive when optimizing your
code, therefore it's typically better to set-up a separate configuration file::

    // Resources/config/plovr/build.js
    {
        "id": "some-unique-id",
        "paths": ["@MyBundle/Resources/public/javascript/"],
        "mode": "ADVANCED",
        "level": "VERBOSE",
        // this is the entry point to your application
        "inputs": "@MyBundle/Resources/public/javascript/app.js",
        
        // this allows you to overwrite Javascript "constants" at compile-time
        "define": {
          "goog.DEBUG": false  
        },
        
        // "@MyBundle" will be expanded to the location of your bundle automatically
        "output-file": "@MyBundle/Resources/public/javascript/build/app.js",
        
        // This allows you to apply some nice copyright at the top of the compiled file.
        // It's also a good idea to wrap the generated code in a closure.
        "output-wrapper": "/**\n * Portions of this code are from the Google Closure Library,\n * received from the Closure Authors under the Apache 2.0 license.\n *\n * All other code is (C) 2011 XYZ\n * All rights reserved.\n */\n(function() {%output%})();",     
        
        "debug": false
    }

You can then compile your Javascript application using the following command::

    php app/console plovr:build @MyBundle/build.js


.. Google Closure Tools: http://code.google.com/closure/
.. Plovr: http://plovr.com/
.. plovr configuration: http://plovr.com/options.html