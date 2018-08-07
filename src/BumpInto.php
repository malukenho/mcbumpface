<?php

declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Generator;
use function array_key_exists;
use function array_merge;
use function file_get_contents;
use function file_put_contents;
use function iterator_to_array;
use function sprintf;
use function str_replace;
use function strpos;

final class BumpInto implements PluginInterface, EventSubscriberInterface
{
    public static function versions(Event $composerEvent) : void
    {
        $io               = $composerEvent->getIO();
        $composer         = $composerEvent->getComposer();
        $rootPackage      = $composer->getPackage();
        $composerJsonFile = $composer->getConfig()->getConfigSource()->getName();

        $contents    = file_get_contents($composerJsonFile);
        $manipulator = new JsonManipulator($contents);

        $lockVersions        = iterator_to_array(self::getInstalledVersions($composer->getLocker(), $rootPackage));
        $requiredVersions    = self::getRequiredVersion($rootPackage);
        $requiredDevVersions = self::getRequiredDevVersion($rootPackage);

        self::updateDependencies($io, $manipulator, 'require', $requiredVersions, $lockVersions);
        self::updateDependencies($io, $manipulator, 'require-dev', $requiredDevVersions, $lockVersions);

        file_put_contents($composerJsonFile, $manipulator->getContents());
    }

    /**
     * @param string[] $requiredVersions
     * @param string[] $lockVersions
     */
    private static function updateDependencies(
        IOInterface $IO,
        JsonManipulator $manipulator,
        string $configKey,
        array $requiredVersions,
        array $lockVersions
    ) : void {
        foreach ($requiredVersions as $package => $version) {
            if (! array_key_exists($package, $lockVersions)) {
                continue;
            }
            $lockVersion = $lockVersions[$package];

            if (false !== strpos($version, ' as ')) {
                continue;
            }

            if (self::isSimilar($version, $lockVersion)) {
                continue;
            }

            $manipulator->addLink($configKey, $package, '^' . $lockVersion, false);

            $IO->write(sprintf(
                'Updating <info>%s</info>%s package from version (<info>%s</info>) to (<info>%s</info>)',
                $package,
                $configKey === 'require-dev' ? ' dev' : '',
                $version,
                '^' . $lockVersion
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // nope.
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'versions',
            ScriptEvents::POST_UPDATE_CMD => 'versions',
        ];
    }

    /**
     * @return Generator|string[]
     */
    private static function getInstalledVersions(Locker $locker, RootPackageInterface $rootPackage) : Generator
    {
        $lockData                 = $locker->getLockData();
        $lockData['packages-dev'] = $lockData['packages-dev'] ?? [];
        foreach (array_merge($lockData['packages'], $lockData['packages-dev']) as $package) {
            yield $package['name'] => $package['version'];
        }

        foreach ($rootPackage->getReplaces() as $replace) {
            $version = $replace->getPrettyConstraint();
            if ($version === 'self.version') {
                $version = $rootPackage->getVersion();
            }
            yield $replace->getTarget() => $version;
        }

        yield $rootPackage->getName() => $rootPackage->getVersion();
    }

    /**
     * @return Generator|string[]
     */
    private static function getRequiredVersion(RootPackageInterface $rootPackage) : array
    {
        return iterator_to_array(self::extractVersions($rootPackage->getRequires()));
    }

    /**
     * @return Generator|string[]
     */
    private static function getRequiredDevVersion(RootPackageInterface $rootPackage) : array
    {
        return iterator_to_array(self::extractVersions($rootPackage->getDevRequires()));
    }

    /**
     * @param Link[] $links
     *
     * @return Generator|string[]
     */
    private static function extractVersions(array $links) : Generator
    {
        foreach ($links as $packageName => $required) {
            // should only consider packages with `/` separator
            // that means that we ignore "php" or "ext-*"
            if (strpos($packageName, '/') === false) {
                continue;
            }

            yield $packageName => $required->getConstraint()->getPrettyString();
        }
    }

    private static function isSimilar(string $version, string $lockVersion) : bool
    {
        return str_replace('^', '', $version) === str_replace('^', '', $lockVersion);
    }
}
