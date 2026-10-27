<?php

namespace AbeTwoThree\LaravelLang\Commands;

use Illuminate\Console\Command;

class LaravelLangCommand extends Command
{
    public $signature = 'laravel-lang';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
