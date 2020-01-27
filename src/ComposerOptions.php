<?php
declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Package\RootPackageInterface;
use function preg_replace;

final class ComposerOptions
{
    public const EXTRA_IDENTIFIER = 'mc-bumpface';

    /**
     * With this parameter, you can configure your project to either keep or drop the version `v` prefix from versions.
     *
     * @var bool
     */
    private $stripVersionPrefixes;

    private function __construct(bool $stripVersionPrefixes)
    {
        $this->stripVersionPrefixes = $stripVersionPrefixes;
    }

    public static function fromRootPackage(RootPackageInterface $package) : self
    {
        $extra = $package->getExtra()[self::EXTRA_IDENTIFIER] ?? [];

        return new self($extra['stripVersionPrefixes'] ?? false);
    }

    public function manipulateVersionIfNeeded(string $version) : string
    {
        if (! $this->stripVersionPrefixes) {
            return $version;
        }

        return (string) preg_replace('/^v(?<version>.*)/', '\1', $version);
    }
}
