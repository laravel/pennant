<?php

namespace Laravel\Pennant\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\FeatureManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pennant:toggle')]
class ToggleCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pennant:toggle
                            {feature : The feature to toggle}
                            {--scope= : The optional scope identifier}
                            {--on : Force activation (instead of toggling)}
                            {--off : Force deactivation (instead of toggling)}
                            {--store= : The store to toggle the feature in}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toggle a feature flag on/off globally or for a specific scope';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $feature = $this->argument('feature');
        $scope = $this->option('scope') ?: null;
        $forceOn = $this->option('on');
        $forceOff = $this->option('off');
        $store = $manager->store($this->option('store'));

        $currentValue = $scope
            ? $store->for($scope)->value($feature)
            : $store->value($feature);

        $isCurrentlyActive = $currentValue === true || ($currentValue !== false && $currentValue !== null);

        if ($forceOn) {
            $newValue = true;
            $action = 'activated';
        } elseif ($forceOff) {
            $newValue = false;
            $action = 'deactivated';
        } else {
            $newValue = ! $isCurrentlyActive;
            $action = $newValue ? 'activated' : 'deactivated';
        }

        $target = $scope ? $store->for($scope) : $store;

        if ($newValue) {
            $target->activate($feature, $newValue);
        } else {
            $target->deactivate($feature);
        }

        $scopePart = $scope ? " for scope '{$scope}'" : ' globally';

        $this->components->info("Feature '{$feature}' {$action}{$scopePart}.");

        $beforeStatus = $isCurrentlyActive ? 'active' : 'inactive';
        $afterStatus  = $newValue ? 'active' : 'inactive';

        $this->line("  Before: {$beforeStatus}");
        $this->line("   After: {$afterStatus}");

        return self::SUCCESS;
    }
}