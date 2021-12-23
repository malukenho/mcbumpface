<?php

declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Exception;
use Generator;
use Symfony\Component\Console\Input\InputInterface;

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
use function str_contains;
use function str_starts_with;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

final class Bumper
{
    private const TEMPLATE_GLOBAL    = '<info>malukenho/mcbumpface</info> %s';
    private const TEMPLATE_FINISHED  = 'Done. All packages are fixed to their locked versions.';
    private const TEMPLATE_NOT_FOUND = 'Package not found (probably scheduled for removal); package bumping skipped.';
    //phpcs:disable Generic.Files.LineLength.TooLong
    private const TEMPLATE_EXPANDING = 'is expanding <info>%s</info>%s package locked version from (<info>%s</info>) to (<info>%s</info>)';
    private const TEMPLATE_UPDATING  = 'is updating <info>%s</info>%s package from version (<info>%s</info>) to (<info>%s</info>)';
    //phpcs:enable Generic.Files.LineLength.TooLong

    private static function writeMessage(IOInterface $io, string $message, string ...$args): void
    {
        $io->write(sprintf(self::TEMPLATE_GLOBAL, sprintf($message, ...$args)));
    }

    /**
     * @throws Exception
     */
    public static function versions(Composer $composer, IOInterface $io, InputInterface $input): void
    {
        if (! file_exists(__DIR__)) {
            self::writeMessage($io, self::TEMPLATE_NOT_FOUND);
            return;
        }

        $locker           = $composer->getLocker();
        $rootPackage      = $composer->getPackage();
        $composerJsonFile = $composer->getConfig()->getConfigSource()->getName();

        $composerLockFile = pathinfo($composerJsonFile, PATHINFO_EXTENSION) === 'json' ? substr(
            $composerJsonFile,
            0,
            -4
        ) . 'lock' : $composerJsonFile . '.lock';

        $contents    = file_get_contents($composerJsonFile);
        $manipulator = new JsonManipulator($contents);

        $lockVersions        = iterator_to_array(self::getInstalledVersions($locker, $rootPackage));
        $requiredVersions    = self::getRequiredVersion($rootPackage);
        $requiredDevVersions = self::getRequiredDevVersion($rootPackage);

        $options = Options::fromRootPackage($rootPackage, $input);
        self::updateDependencies($io, $manipulator, 'require', $requiredVersions, $lockVersions, $options);
        self::updateDependencies($io, $manipulator, 'require-dev', $requiredDevVersions, $lockVersions, $options);

        $contents    = $manipulator->getContents();
        $contentHash = Locker::getContentHash($contents);

        file_put_contents($composerJsonFile, $contents);

        self::updateLockContentHash($composerLockFile, $contentHash);
        self::writeMessage($io, self::TEMPLATE_FINISHED);
    }

    /**
     * @return Generator<string, string>
     */
    private static function getInstalledVersions(?Locker $locker, RootPackageInterface $rootPackage): Generator
    {
        if ($locker === null) {
            return;
        }
        $lockData                 = $locker->getLockData();
        $lockData['packages-dev'] = (array) ($lockData['packages-dev'] ?? []);

        /** @var array<string, string> $package */
        foreach (array_merge((array) $lockData['packages'], $lockData['packages-dev']) as $package) {
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
     * @return array<string, string>
     */
    private static function getRequiredVersion(RootPackageInterface $rootPackage): array
    {
        return iterator_to_array(self::extractVersions($rootPackage->getRequires()));
    }

    /**
     * @param array<string, Link> $links
     * @return Generator<string, string>
     */
    private static function extractVersions(array $links): Generator
    {
        foreach ($links as $packageName => $required) {
            // should only consider packages with `/` separator
            // that means that we ignore "php" or "ext-*"
            if (! str_contains($packageName, '/')) {
                continue;
            }

            yield $packageName => $required->getConstraint()->getPrettyString();
        }
    }

    /**
     * @return array<string, string>
     */
    private static function getRequiredDevVersion(RootPackageInterface $rootPackage): array
    {
        return iterator_to_array(self::extractVersions($rootPackage->getDevRequires()));
    }

    /**
     * @param array<string, string> $requiredVersions
     * @param array<string, string> $lockVersions
     */
    private static function updateDependencies(
        IOInterface $io,
        JsonManipulator $manipulator,
        string $configKey,
        array $requiredVersions,
        array $lockVersions,
        Options $options
    ): void {
        foreach ($requiredVersions as $package => $version) {
            // Skip complex ranges for now
            if (
                ! array_key_exists($package, $lockVersions)
                || str_contains($version, ',')
                || str_contains($version, '|')
                || str_starts_with($version, '~')
                || str_contains($version, ' as ')
                || preg_match('~^dev-.+@dev$|@dev~', $version) === 1
            ) {
                continue;
            }

            $lockVersion = $lockVersions[$package];
            if (self::isSimilar($version, $lockVersion) || str_starts_with($lockVersion, 'dev')) {
                continue;
            }

            $lockVersion = $options->manipulateVersionIfNeeded($lockVersion);
            if (self::isLockedVersion($version)) {
                $manipulator->addLink($configKey, $package, $lockVersion, false);

                self::writeMessage(
                    $io,
                    self::TEMPLATE_EXPANDING,
                    $package,
                    $configKey === 'require-dev' ? ' dev' : '',
                    $version,
                    $lockVersion
                );

                continue;
            }

            $manipulator->addLink($configKey, $package, '^' . $lockVersion, false);

            self::writeMessage(
                $io,
                self::TEMPLATE_UPDATING,
                $package,
                $configKey === 'require-dev' ? ' dev' : '',
                $version,
                '^' . $lockVersion
            );
        }
    }

    private static function isSimilar(string $version, string $lockVersion): bool
    {
        return trim($version, '^v') === trim($lockVersion, '^v');
    }

    private static function isLockedVersion(string $version): bool
    {
        // Just by checking if the version is numeric
        // we guarantee that $version is a string
        // with numbers and dots.
        return is_numeric($version);
    }

    /**
     * @throws Exception
     */
    private static function updateLockContentHash(string $composerLockFile, string $contentHash): void
    {
        $lockFile = new JsonFile($composerLockFile);
        /** @var array<string, mixed> $lockData */
        $lockData = $lockFile->read();

        $lockData['content-hash'] = $contentHash;

        $lockFile->write($lockData);
    }
}
