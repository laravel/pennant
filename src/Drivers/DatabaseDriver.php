<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Database\Connection;

class DatabaseDriver
{
    /**
     * The database connection.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $db;

    /**
     * Create a new Database Driver instance.
     *
     * @param  \Illuminate\Database\Connection $db
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }
}
