# Composer configuration for external non-Composer-compatible packages

## Overview
The Composer dependency injector works well if the composer.json exists in the main directory of the package. 
But life is not a lollipop and sometimes situations are coming, when the package belongs not to the 
Composer's dependencies (like a private package) or the dependent composer.json file is not placed in 
the main package directory.  

## Target
This solution should cover the case of an existing composer.json file, which is not placed in the main package directory 
(standard private packages don't have any Composer dependencies).

## Possible solutions

### Manual place the composer.json in the main directory
The composer.json can be moved from a subdirectory to the main directory of the package. This will work with own packages,
as the developer has the authority to make changes in the repository. Simply move the file to the main directory, 
commit, push and the Composer will process it in the next update process.

### Automatically generate the needed composer.json file
The main problem is, the packages are mainly maintained via 3rd party developers and the authority to change belongs 
to them. We can, of course, kindly ask to create them in the way the good Lord intended, but we cannot expect they'll 
do it. If we need such a package, and there is no alternative - we have to handle the problem.

#### Current solution
To use an external package in our application, a specific repository type has to be created: the package.
A package is (after the official Composer docs) : _If you depend on a project that does not have any support for 
composer whatsoever you can define the package inline using a package repository. You basically inline the 
composer.json object_.

This sample application covers the subject of processing Composer dependencies if the composer.json is hidden somewhere in 
the directory structure.

This demo contains 3 repositories in GitHub:

* The main "application" (this repository): https://github.com/vnrld/sample.git

* vnrld/lib1 package : misplaced composer.json : https://github.com/vnrld/lib1.git

* vnrld/lib2 package : correct composer.json : https://github.com/vnrld/lib2.git

##### Composer.json
```
{
  "name": "vnrld/sample",
  "description": "Sample application which contains a non-composer-compatible package",
  "minimum-stability": "stable",
  "license": "proprietary",
  "authors": [
    {
      "name": "Lukas Dabkowski",
      "email": "ld@vnr.com"
    }
  ],
  "autoload": {
    "psr-4": {
      "ComposerScripts\\": "."
    }
  },
  "repositories": [
    {
      "type": "package",
      "package": {
        "name": "vnrld/lib1",
        "version": "1.0.1",
        "type": "my-plugin",
        "source": {
          "url": "https://github.com/vnrld/lib1.git",
          "type": "git",
          "reference": "1.0.1"
        }
      }
    },
    {
      "type": "vcs",
      "url": "https://github.com/vnrld/lib2.git"
    }
  ],
  "extra": {
    "installer-types": [
      "my-plugin"
    ],
    "installer-paths": {
      "plugins/{$name}": [
        "type:my-plugin"
      ]
    },
    "merge-plugin": {
      "include": [
        "plugins/*/composer.json"
      ],
      "recurse": true,
      "replace": false,
      "ignore-duplicates": false,
      "merge-dev": true,
      "merge-extra": true,
      "merge-extra-deep": true,
      "merge-scripts": true
    }
  },
  "require": {
    "ext-json": "*",
    "ext-xml" : "*",
    "vnrld/lib1": "^1.0",
    "vnrld/lib2": "dev-master@dev",
    "composer/installers": "1.7.*",
    "oomphinc/composer-installers-extender": "1.1.*",
    "wikimedia/composer-merge-plugin": "^1.4"
  },
  "scripts": {
    "pre-command-run": [
      "ComposerScripts\\Executor::disableInstallingFromCacheForExternalPackages"
    ],
    "post-package-install": [
      "ComposerScripts\\Executor::generateComposerJson"
    ]
  }
}

```

##### Composer.json : Repositories
```
"repositories": [
    {
       "type": "package",
       "package": {
         "name": "vnrld/lib1",
         "version": "1.0.1",
         "type": "my-plugin",
         "source": {
           "url": "https://github.com/vnrld/lib1.git",
           "type": "git",
           "reference": "1.0.1"
         }
       }
     },
     ...
]
```
To use a Composer incompatible repository it has to be marked as "package". This setting will force the Composer not to 
search for the composer.json file after the repository will be cloned.
The minimum package configuration contains:

* _name_ : name of the package with the namespace. MUST equal to the repository, here: vnrld/lib1
* _version_: which version of the package should be cloned (a version can be set as a branch or a tag in the target repository)
* _type_ : if the repository needs to be of special type (e.g. for the installer). Defaults to "library"
* _source_: from where should the code come from. Url, Type and Reference parameters are mandatory. The reference is the version
which should be cloned.

##### Composer.json : Installer paths

###### Configuration

```
"extra": {
    "installer-types": [
      "my-plugin"
    ],
    "installer-paths": {
      "plugins/{$name}": [
        "type:my-plugin"
      ]
    },
    ...
  }
```

* _installer-types_ : describes the types which shouldn't be placed in the vendor directory
* _installer-path_ : describes the target directory of the installed type. The variables (e.g. $name) are taken from the 
package configuration and represent the configuration keys. See the external packages composer.json files for reference.

##### Packages
* "composer/installers": "1.7.*"
* "oomphinc/composer-installers-extender": "1.1.*",

##### Composer.json : Wikimedia merge plugin

###### Configuration
```
"extra": {
    ...
    "merge-plugin": {
      "include": [
        "plugins/*/composer.json"
      ],
      "recurse": true,
      "replace": false,
      "ignore-duplicates": false,
      "merge-dev": true,
      "merge-extra": true,
      "merge-extra-deep": true,
      "merge-scripts": true
    }
  },
```
In this example the include files cover the subdirectories of the plugin directory. Of course it is possible to provide a 
path to every possible place, where the composer.json should be found. As the example should be simple, and the scripts 
are creating the needed structure - there is no need to add more paths.

###### Source and documentation
https://github.com/wikimedia/composer-merge-plugin

##### Composer.json : Scripts

##### Configuration

```
"scripts": {
    "pre-command-run": [
      "ComposerScripts\\Executor::disableInstallingFromCacheForExternalPackages"
    ],
    "post-package-install": [
      "ComposerScripts\\Executor::generateComposerJson"
    ]
  }
```

The scripts were user in two events. They prepare and process particular dependencies.

###### pre-command-run
```
/**
     * Disables the cache storage for the external packages
     *
     * @param PreCommandRunEvent $event Event object
     *
     * @return void
     */
    public static function disableInstallingFromCacheForExternalPackages(PreCommandRunEvent $event): void
    {

        // Set up the Composer application
        $application = new Application();
        $application->setAutoExit(false);

        // create a fresh Composer instance to avoid overlapping with the running instance
        $composer = Factory::create($application->getIO(), null, false);

        // Get the cache directory
        $cacheDir = $composer->getConfig()->get('cache-vcs-dir');

        // Iterate over the cache directory
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);

        // Search and destroy, oops, find the repositories of the package type
        $repositories = $composer->getConfig()->getRepositories();

        foreach ($repositories as $repository) {

            if (($repository['type'] ?? null) === 'package') {
                self::$packages[] = $repository['package']['name'];
                break;
            }
        }

        // if any external package exists
        foreach (self::$packages as $package) {

            /**
             * Delete the cached entries related to the package. This will force composer to fetch the newest instance
             * directly from the repository instead of using the cached version.
             *
             * @var \SplFileObject $file
             */
            foreach ($it as $file) {
                if (strpos($file->getPathname(), str_replace('/', '-', $package) . '.git') !== false) {
                    $file->isDir() ? rmdir($file) : unlink($file);
                }
            }

            // if the next executed command is "update"
            if ($event->getCommand() === 'update') {

                // run the application to fetch the dependent package from the repository
                $input = new ArrayInput([
                    'command' => 'require',
                    'packages' => is_array($package) ? $package : [$package]
                ]);
                $input->setInteractive(false);

                $application->run($input);
            }

        }
    }
```

###### post-package-install
```
/**
     * Generates the composer.json file in the main package directory
     *
     * @param PackageEvent $event Package event
     *
     * @return void
     *
     * @throws Exception If the copy process of the composer.json file fails
     */
    public static function generateComposerJson(PackageEvent $event): void
    {

        // Current package
        $installedPackage = $event->getOperation()->getPackage();

        // Composer instance
        $composer = $event->getComposer();

        // Installation manager
        $installationManager = $composer->getInstallationManager();

        // List of the repositories
        $repositories = $composer->getConfig()->getRepositories();

        $currentType = '';

        // Find the type of the repository
        foreach ($repositories as $repository) {

            if (($repository['package']['name'] ?? null) === $installedPackage->getName()) {
                $currentType = $repository['package']['type'];
                break;
            }
        }

        // Only if the types are equal
        if ($installedPackage->getType() === $currentType) {

            // Set the package's target directory
            $installedPackage->setTargetDir($installationManager->getInstallPath($installedPackage));

            // Prepare target file name
            $packageComposerJson = $installedPackage->getTargetDir() . '/composer.json';

            // Iterate over the target directory
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($installedPackage->getTargetDir()));

            foreach ($it as $file) {

                $pathName = $file->getPathname();

                // if the first composer file was found
                if (basename($pathName) === 'composer.json') {

                    // copy it to the main directory
                    if (copy($pathName, $packageComposerJson)) {
                        break;
                    }

                    // or if the copying did not work, shout.
                    throw new Exception('Package "' . $installedPackage->getName() . '" couldn\'t be processed! 
                       Copy from "' . $pathName . '" to "' . $packageComposerJson . '" failed!');
                }
            }
        }
    }
```
