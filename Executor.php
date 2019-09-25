<?php

namespace ComposerScripts;

use Composer\Composer;
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Composer\Factory;
use Composer\EventDispatcher\Event as DispatcherEvent;
use Composer\Script\Event;
use Composer\Installer;
use Composer\Installer\PackageEvent;
use Composer\Installer\InstallerEvent;
use Composer\Plugin\PreCommandRunEvent;
use Composer\Plugin\CommandEvent;

class Executor
{
    static protected $lockFile = __DIR__ . '/composer.packages.lock';

    static protected $eventId;

    static protected $packages;

    public static function preCommandRun(PreCommandRunEvent $event)
    {

        $application = new Application();
        $application->setAutoExit(false);

        $composer = Factory::create($application->getIO(), null, false);

        // Composer instance
        $cacheDir = $composer->getConfig()->get('cache-vcs-dir');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

        $repositories = $composer->getConfig()->getRepositories();

        foreach ($repositories as $repository) {

            if (($repository['type'] ?? null) === 'package') {
                self::$packages[] = $repository['package']['name'];
                break;
            }
        }

        foreach (self::$packages as $package) {
            /**
             * @var \SplFileObject $file
             */
            foreach ($it as $file) {
                if (strpos($file->getPathname(), str_replace('/', '-', $package) . '.git') !== false) {

                    echo $file->getPathname() . ' deleted!' . PHP_EOL;
                    $file->isDir() ? rmdir($file) : unlink($file);
                }
            }

            if ($event->getCommand() === 'update') {

                $input = new ArrayInput(['command' => 'require', 'packages' => is_array($package) ? $package : [$package]]);
                $input->setInteractive(false);

                $application->run($input);
            }

        }
    }


    public static function preDependenciesSolving(CommandEvent $event)
    {

        echo strtoupper(__METHOD__) . PHP_EOL;
        print_r(get_class_methods($event));

    }

    public static function installPackageDependencies(PackageEvent $event)
    {


        echo 'Event ID: ' . self::$eventId . PHP_EOL;

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

            echo strtoupper(__METHOD__) . PHP_EOL;

            $installedPackage->setTargetDir($installationManager->getInstallPath($installedPackage));

            $packageComposerJson = $installedPackage->getTargetDir() . '/composer.json';

            $dependentPackages = [];

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($installedPackage->getTargetDir()));

            foreach ($it as $file) {

                $pathName = $file->getPathname();

                if (basename($pathName) === 'composer.json') {
                    if (copy($pathName, $packageComposerJson)) {
                        $dependentPackages[$pathName] = [
                            'file' => $packageComposerJson,
                            'package' => $installedPackage->getName()
                        ];
                    }
                }
            }

            $fileContents = json_encode($dependentPackages, JSON_THROW_ON_ERROR) . PHP_EOL;

            if (file_exists(self::$lockFile)) {
                $fileContents = str_replace($fileContents, '',
                    file_get_contents(self::$lockFile));
            }

            file_put_contents(self::$lockFile, $fileContents, FILE_APPEND);

           $installer = Installer::create(
                $event->getIO(),
                // Create a new Composer instance to ensure full processing of
                // the merged files.
                Factory::create($event->getIO(), null, false)
            );

            $installer->setUpdate(true);
            $installer->run();

        }

        // do stuff
    }

    public static function updatePackageDependencies(PackageEvent $event)
    {
        echo strtoupper(__METHOD__) . PHP_EOL;

        $initialPackage = $event->getOperation()->getInitialPackage();
        $targetPackage = $event->getOperation()->getTargetPackage();

        var_dump($initialPackage->getName());
        var_dump($targetPackage->getName());

        // do stuff
    }

    /**
     * Removes the composer.json files that were copied in the previous method
     *
     */
    public static function registerShutdownFunction(): void
    {
        echo strtoupper(__METHOD__) . PHP_EOL;
        register_shutdown_function([self::class, 'shutdown']);
    }

    public static function shutdown()
    {
        echo strtoupper(__METHOD__) . PHP_EOL;

        var_dump(self::$packages);

        //unlink('composer.lock');

        /* if (file_exists(self::$lockFile)) {
            $files = file(self::$lockFile);

            foreach ($files as $fileJson) {

                $fileArray = json_decode(trim($fileJson), true, 512, JSON_THROW_ON_ERROR);

                foreach ($fileArray as $srcFile => $targetFile) {

                    if (file_exists($srcFile)) {
                        unlink($targetFile);
                        continue;
                    }
                }

            }

            unlink(self::$lockFile);
        } */
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