<?php
declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Package\RootPackageInterface;
use function preg_replace;

final class ComposerOptions
{
    public const EXTRA_IDENTIFIER = 'mc-bumpface';

    /**
     * With this parameter, you can configure your project to either keep or drop non-numbers from version.
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

    /**
     * @return array<string,mixed>
     */
    public function array() : array
    {
        $options = [];
        foreach ($this as $property => $value) {
            $options[$property] = $value;
        }

        return $options;
    }
}
