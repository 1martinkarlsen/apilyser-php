<?php

namespace Apilyser\Util;

use Symfony\Component\Console\Output\OutputInterface;

class Logger
{
    public function __construct(
        private OutputInterface $output,
        private bool $info
    ) {
    }

    public function log(string $message): void {
        $this->output->writeln($message);
    }

    public function info(string $message): void {
        if ($this->info) {
            $this->log($message);
        }
    }

}