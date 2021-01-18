<?php

namespace JTMcC\AtomicDeployments\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;

class BaseCommand extends Command
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        app(ConsoleOutput::class)->setOutput($this);

        return parent::run($input, $output);
    }
}