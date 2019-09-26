<?php

namespace ComposerScripts;

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PreCommandRunEvent;
use Exception;

class Executor
{
    /**
     * Lock file for external packages, that don't have the composer.json file in the main directory
     *
     * @var string
     */
    static protected $lockFile = __DIR__ . '/composer.packages.lock';

    /**
     * List of packages
     *
     * @var array
     */
    static protected $packages;

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
}