<?php

namespace Laravel\Package\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel-package:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install LaravelPackage';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('LaravelPackage installed successfully.');
    }
}
