<?php

namespace Laravel\Pennant\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\FeatureManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pennant:purge', aliases: ['pennant:clear'])]
class PurgeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pennant:purge
                            {features?* : The features to purge}
                            {--except=* : The features that should be excluded from purging}
                            {--except-registered : Purge all features except those registered}
                            {--store= : The store to purge the features from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Pennant features from storage';

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected $aliases = ['pennant:clear'];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $store = $manager->store($this->option('store'));

        $features = $this->argument('features') ?: null;

        $except = collect($this->option('except'))
            ->when($this->option('except-registered'), fn ($except) => $except->merge($store->defined()))
            ->unique()
            ->all();

        if ($except) {
            $features = collect($features ?: $store->stored())
                ->flip()
                ->forget($except)
                ->flip()
                ->values()
                ->all();
        }

        $store->purge($features);

        with($features ?: ['All features'], function ($names) {
            $this->components->info(implode(', ', $names).' successfully purged from storage.');
        });

        return self::SUCCESS;
    }
}
