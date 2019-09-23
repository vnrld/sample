<?php

namespace ComposerScripts;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;
use Composer\Installer\InstallerEvent;

class Executor
{
    static protected $lockFile = __DIR__ . '/composer.script.lock';

    public static function processDependencies(PackageEvent $event)
    {
        // Get the common objects
        [$installedPackage, $installationManager, $repositories] = self::getCommonObjects($event);

        $currentType = '';

        foreach ($repositories as $repository) {

            if (($repository['package']['name'] ?? null) === $installedPackage->getName()) {
                $currentType = $repository['package']['type'];
                break;
            }
        }

        if ($installedPackage->getType() === $currentType) {

            $installedPackage->setTargetDir($installationManager->getInstallPath($installedPackage));

            $packageComposerJson = $installedPackage->getTargetDir() . '/composer.json';

            if (!file_exists($packageComposerJson)) {

                $dependendPackages = [];

                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($installedPackage->getTargetDir()));

                foreach ($it as $file) {

                    $pathName = $file->getPathname();

                    if (basename($pathName) === 'composer.json') {
                        if (copy($pathName, $packageComposerJson)) {
                            $dependendPackages[$pathName] = ['file' => $packageComposerJson, 'first_run' => true];
                        }
                    }
                }

                file_put_contents(self::$lockFile, json_encode($dependendPackages, JSON_THROW_ON_ERROR));
            }
        }

        // do stuff
    }

    /**
     * Removes the composer.json files that were copied in the previous method
     *
     */
    public static function removeUnwantedComposerFiles() : void
    {
        if (file_exists(self::$lockFile)) {
            $files = json_decode(file_get_contents(self::$lockFile), true, 512, JSON_THROW_ON_ERROR);

            foreach ($files as $srcFile => &$targetFile) {

                $firstRun = $targetFile['first_run'];

                if ($firstRun === false && file_exists($srcFile)) {
                    unlink($targetFile['file']);

                    if (file_exists(self::$lockFile)) {
                        unlink(self::$lockFile);
                    }

                    continue;
                }

                if ($firstRun) {
                    $targetFile['first_run'] = false;
                }
            }

            unset($targetFile);

            file_put_contents(self::$lockFile, json_encode($files, JSON_THROW_ON_ERROR));
        }

    }

    /**
     * @param PackageEvent $event
     *
     * @return array
     */
    protected static function getCommonObjects(PackageEvent $event): array
    {
        // Current package
        $installedPackage = $event->getOperation()->getPackage();

        // Composer instance
        $composer = $event->getComposer();

        // Installation manager
        $installationManager = $composer->getInstallationManager();

        // List of the repositories
        $repositories = $composer->getConfig()->getRepositories();

        return [$installedPackage, $installationManager, $repositories];
    }
}