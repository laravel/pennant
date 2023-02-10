<?php

namespace Laravel\Pennant\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\FeatureManager;

class PurgeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pennant:purge
                            {features?* : The features to purge}
                            {--store= : The store to purge the features from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge features from storage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $manager->store($this->option('store'))->purge($this->argument('features') ?: null);

        with($this->argument('features') ?: ['All features'], function ($names) {
            $this->components->info(implode(', ', $names).' successfully purged from storage.');
        });

        return self::SUCCESS;
    }
}
