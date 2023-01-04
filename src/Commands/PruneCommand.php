<?php

namespace Laravel\Feature\Commands;

use Illuminate\Console\Command;
use Laravel\Feature\FeatureManager;

class PruneCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pennant:prune
                            {feature? : The feature to prune}
                            {--driver= : The driver to prune the feature from}';

    /**
     * Execute the console command.
     *
     * @param  \Laravel\Feature\FeatureManager  $manager
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $manager->driver($this->option('driver'))->prune($this->argument('feature'));

        $this->components->info("{$this->argument('feature')} successfully prunned.");

        return self::SUCCESS;
    }
}
