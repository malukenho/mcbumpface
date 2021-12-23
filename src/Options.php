<?php

declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Input\InputInterface;

use function array_merge;
use function preg_replace;

final class Options
{
    public const IDENTIFIER          = 'mcbumpface';
    public const OPTION_STRIP_PREFIX = 'stripVersionPrefix';
    public const DEFAULT_OPTIONS     = [
        self::OPTION_STRIP_PREFIX => true,
    ];

    private function __construct(private bool $stripVersionPrefix)
    {
    }

    public function stripVersionPrefix(): bool
    {
        return $this->stripVersionPrefix;
    }

    public static function fromRootPackage(RootPackageInterface $package, InputInterface $input): self
    {
        $options = array_merge(
            self::DEFAULT_OPTIONS,
            (array) ($package->getExtra()[self::IDENTIFIER] ?? []),
            $input->getOptions(),
        );

        return new self((bool) $options[self::OPTION_STRIP_PREFIX]);
    }

    public function manipulateVersionIfNeeded(string $version): string
    {
        if (! $this->stripVersionPrefix) {
            return $version;
        }

        return (string) preg_replace('/^v(?<version>.*)/', '\1', $version);
    }
}
