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
                            {--scope= : The optional scope identifier (e.g. user:123, team:abc)}
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
        $scopeInput = $this->option('scope');
        $forceOn = $this->option('on');
        $forceOff = $this->option('off');
        $store = $manager->store($this->option('store'));

        $scope = $scopeInput !== null ? $scopeInput : null;

        $currentValue = $scope !== null
            ? $store->for($scope)->value($feature)
            : $store->value($feature);

        $isCurrentlyActive = $currentValue === true || $currentValue !== false && $currentValue !== null;

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

        if ($scope !== null) {
            if ($newValue) {
                $store->for($scope)->activate($feature, $newValue);
            } else {
                $store->for($scope)->deactivate($feature);
            }
            $scopeMsg = " for scope '{$scope}'";
        } else {
            if ($newValue) {
                $store->activate($feature, $newValue);
            } else {
                $store->deactivate($feature);
            }
            $scopeMsg = ' globally';
        }

        $this->components->info("Feature '{$feature}' {$action}{$scopeMsg}.");

        $this->line("  Before: " . ($isCurrentlyActive ? '<fg=green>active</>' : '<fg=red>inactive</>'));
        $this->line("   After: " . ($newValue ? '<fg=green>active</>' : '<fg=red>inactive</>'));

        return self::SUCCESS;
    }
}