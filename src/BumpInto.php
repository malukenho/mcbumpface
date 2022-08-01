<?php

declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
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
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_numeric;
use function iterator_to_array;
use function pathinfo;
use function preg_match;
use function sprintf;
use function strpos;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

final class BumpInto implements PluginInterface, EventSubscriberInterface
{
    public static function versions(Event $composerEvent): void
    {
        $io = $composerEvent->getIO();

        if (! file_exists(__DIR__)) {
            $io->write('<info>malukenho/mcbumpface:</info> Package not found (probably scheduled for removal); package bumping skipped.');

            return;
        }

        $composer         = $composerEvent->getComposer();
        $locker           = $composer->getLocker();
        $rootPackage      = $composer->getPackage();
        $composerJsonFile = $composer->getConfig()->getConfigSource()->getName();

        $composerLockFile = pathinfo($composerJsonFile, PATHINFO_EXTENSION) === 'json'
            ? substr($composerJsonFile, 0, -4) . 'lock'
            : $composerJsonFile . '.lock';

        $contents    = file_get_contents($composerJsonFile);
        $manipulator = new JsonManipulator($contents);

        $lockVersions        = iterator_to_array(self::getInstalledVersions($locker, $rootPackage));
        $requiredVersions    = self::getRequiredVersion($rootPackage);
        $requiredDevVersions = self::getRequiredDevVersion($rootPackage);

        $options = ComposerOptions::fromRootPackage($rootPackage);
        self::updateDependencies($io, $manipulator, 'require', $requiredVersions, $lockVersions, $options);
        self::updateDependencies($io, $manipulator, 'require-dev', $requiredDevVersions, $lockVersions, $options);

        $contents    = $manipulator->getContents();
        $contentHash = Locker::getContentHash($contents);

        file_put_contents($composerJsonFile, $contents);

        self::updateLockContentHash($composerLockFile, $contentHash);
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
        array $lockVersions,
        ComposerOptions $options
    ): void {
        foreach ($requiredVersions as $package => $version) {
            $constraintPrefix = '^';

            if (! array_key_exists($package, $lockVersions)) {
                continue;
            }

            // Skip complex ranges for now
            if (strpos($version, ',') !== false) {
                continue;
            }

            if (strpos($version, '|') !== false) {
                continue;
            }

            if (strpos($version, '~') === 0 && $options->shouldKeepVersionConstraintPrefix()) {
                $constraintPrefix = '~';
            }

            if (strpos($version, ' as ') !== false) {
                continue;
            }

            if (preg_match('~^dev-.+@dev$|@dev~', $version) === 1) {
                continue;
            }

            $lockVersion = $lockVersions[$package];

            if (self::isSimilar($version, $lockVersion)) {
                continue;
            }

            if ($lockVersion === 'dev-master') {
                continue;
            }

            $lockVersion = $options->manipulateVersionIfNeeded($lockVersion);

            if (self::isLockedVersion($version)) {
                $manipulator->addLink($configKey, $package, $lockVersion, false);

                $IO->write(sprintf(
                    '<info>malukenho/mcbumpface</info> is expanding <info>%s</info>%s package locked version from (<info>%s</info>) to (<info>%s</info>)',
                    $package,
                    $configKey === 'require-dev' ? ' dev' : '',
                    $version,
                    $lockVersion
                ));

                continue;
            }

            $manipulator->addLink($configKey, $package, $constraintPrefix . $lockVersion, false);

            $IO->write(sprintf(
                '<info>malukenho/mcbumpface</info> is updating <info>%s</info>%s package from version (<info>%s</info>) to (<info>%s</info>)',
                $package,
                $configKey === 'require-dev' ? ' dev' : '',
                $version,
                '^' . $lockVersion
            ));
        }
    }

    private static function isLockedVersion(string $version): bool
    {
        // Just by checking if the version is numeric
        // we guarantee that $version is a string
        // with numbers and dots.
        return is_numeric($version);
    }

    private static function updateLockContentHash(string $composerLockFile, string $contentHash): void
    {
        $lockFile = new JsonFile($composerLockFile);
        $lockData = $lockFile->read();

        $lockData['content-hash'] = $contentHash;

        $lockFile->write($lockData);
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // nope.
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'versions',
            ScriptEvents::POST_UPDATE_CMD => 'versions',
        ];
    }

    /**
     * @return Generator|string[]
     */
    private static function getInstalledVersions(Locker $locker, RootPackageInterface $rootPackage): Generator
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

    /** @return string[] */
    private static function getRequiredVersion(RootPackageInterface $rootPackage): array
    {
        return iterator_to_array(self::extractVersions($rootPackage->getRequires()));
    }

    /** @return string[] */
    private static function getRequiredDevVersion(RootPackageInterface $rootPackage): array
    {
        return iterator_to_array(self::extractVersions($rootPackage->getDevRequires()));
    }

    /**
     * @param Link[] $links
     *
     * @return Generator|string[]
     */
    private static function extractVersions(array $links): Generator
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

    private static function isSimilar(string $version, string $lockVersion): bool
    {
        return trim($version, '^v') === trim($lockVersion, '^v');
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
