<?php

namespace Laravel\Feature;

use Illuminate\Database\Connection;

class DatabaseDriver
{
    /**
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * @param \Illuminate\Database\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function isActive($feature, $scope)
    {
        return $this->connection->table('features')
            ->where('name', $feature)
            ->where('scope', $scope)
            ->where
    }

    /**
     * @var string $feature
     * @return bool
     */
    public function activate($feature)
    {
        $this->activateMany([$feature]);
    }

    /**
     * @var string $feature
     * @return bool
     */
    public function deactivate($feature)
    {
        $this->deactivateMany([$feature]);
    }

    /**
     * @param array<string> $features
     * @return void
     */
    public function activateMany($features)
    {
        $features = collect($features)->map(fn ($feature) => [
            'name' => $feature,
            'scope' => null,
            'active' => true,
        ]);

        $this->connection->table('features')->upsert($features->all(), ['name', 'scope'], ['active']);
    }

    /**
     * @param array<string> $features
     * @return void
     */
    public function deactivateMany($features)
    {
        $features = collect($features)->map(fn ($feature) => [
            'name' => $feature,
            'scope' => null,
            'active' => false,
        ]);

        $this->connection->table('features')->upsert($features->all(), ['name', 'scope'], ['active']);
    }
}
