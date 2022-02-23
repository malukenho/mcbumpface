<?php

declare(strict_types=1);

namespace Malukenho\McBumpface;

use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Input\InputInterface;

use function array_merge;
use function filter_var;
use function preg_replace;

use const FILTER_VALIDATE_BOOLEAN;

final class Options
{
    public const IDENTIFIER                            = 'mcbumpface';
    public const OPTION_STRIP_PREFIX                   = 'stripVersionPrefix';
    public const OPTION_KEEP_VERSION_CONSTRAINT_PREFIX = 'keepVersionConstraintPrefix';
    public const DEFAULT_OPTIONS                       = [
        self::OPTION_STRIP_PREFIX                   => true,
        self::OPTION_KEEP_VERSION_CONSTRAINT_PREFIX => false,
    ];

    private function __construct(private bool $stripVersionPrefix, private bool $keepVersionConstraintPrefix)
    {
    }

    public function stripVersionPrefix(): bool
    {
        return $this->stripVersionPrefix;
    }

    public static function fromRootPackage(RootPackageInterface $package, InputInterface $input): self
    {
        $options = array_merge(self::DEFAULT_OPTIONS, (array) ($package->getExtra()[self::IDENTIFIER] ?? []));

        /** @var string|null $stripVersionPrefix */
        $stripVersionPrefix = $input->getOption(self::OPTION_STRIP_PREFIX);
        if ($stripVersionPrefix !== null) {
            $options[self::OPTION_STRIP_PREFIX] = filter_var($stripVersionPrefix, FILTER_VALIDATE_BOOLEAN);
        }

        return new self(
            (bool) $options[self::OPTION_STRIP_PREFIX],
            (bool) $options[self::OPTION_KEEP_VERSION_CONSTRAINT_PREFIX]
        );
    }

    public function manipulateVersionIfNeeded(string $version): string
    {
        if (! $this->stripVersionPrefix) {
            return $version;
        }

        return (string) preg_replace('/^v(?<version>.*)/', '\1', $version);
    }

    public function shouldKeepVersionConstraintPrefix(): bool
    {
        return $this->keepVersionConstraintPrefix;
    }
}
