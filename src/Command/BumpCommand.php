<?php

declare(strict_types=1);

namespace Malukenho\McBumpface\Command;

use Composer\Command\BaseCommand;
use Malukenho\McBumpface\Bumper;
use Malukenho\McBumpface\Options;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BumpCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct(Options::IDENTIFIER);
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Sync the composer.lock and composer.json versions, resulting in faster package resolutions.'
        );

        $this->addOption(
            Options::OPTION_STRIP_PREFIX,
            's',
            InputOption::VALUE_REQUIRED,
            'Strip "v" prefix from versions (e.g. "^v1.3.0" becomes "^1.3.0").'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->getComposer();
        if ($composer === null) {
            return 1;
        }

        Bumper::versions($composer, $this->getIO(), $input);
        return 0;
    }
}
