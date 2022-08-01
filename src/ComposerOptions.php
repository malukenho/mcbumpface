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
     * phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     *
     * @var bool
     */
    private $stripVersionPrefixes;

    /**
     * With this parameter, you can configurate if the version constraint in your composer.json will be kept or
     * to be replaced with `^`
     *
     * E.g.: `~2.1` will be replaced with `^2.1`
     * Setting this option to true will keep `~2.1`
     *
     * phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     *
     * @var bool
     */
    private $keepVersionConstraintPrefix;

    private function __construct(bool $stripVersionPrefixes, bool $keepVersionConstraintPrefix)
    {
        $this->stripVersionPrefixes        = $stripVersionPrefixes;
        $this->keepVersionConstraintPrefix = $keepVersionConstraintPrefix;
    }

    public static function fromRootPackage(RootPackageInterface $package): self
    {
        $extra = $package->getExtra()[self::EXTRA_IDENTIFIER] ?? [];

        return new self(
            $extra['stripVersionPrefixes'] ?? false,
            $extra['keepVersionConstraintPrefix'] ?? false
        );
    }

    public function manipulateVersionIfNeeded(string $version): string
    {
        if (! $this->stripVersionPrefixes) {
            return $version;
        }

        return (string) preg_replace('/^v(?<version>.*)/', '\1', $version);
    }

    public function shouldKeepVersionConstraintPrefix(): bool
    {
        return $this->keepVersionConstraintPrefix;
    }
}
