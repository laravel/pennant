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
                            {feature? : The feature to purge}
                            {--store= : The store to purge the feature from}';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $manager->store($this->option('store'))->purge($this->argument('feature'));

        with($this->argument('feature') ?? 'All features', function ($name) {
            $this->components->info("{$name} successfully purged from storage.");
        });

        return self::SUCCESS;
    }
}
