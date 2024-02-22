<?php

namespace Laravel\Pennant\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\FeatureManager;

class PruneCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pennant:prune
                            {--store= : The store to purge the features from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove undefined Pennant features from storage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $manager->store($this->option('store'))->prune();

        $this->components->info('Features successfully pruned from storage.');

        return self::SUCCESS;
    }
}
