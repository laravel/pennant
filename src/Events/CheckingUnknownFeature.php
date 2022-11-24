<?php

namespace Laravel\Feature\Events;

use Illuminate\Queue\SerializesModels;

class CheckingUnknownFeature
{
    use SerializesModels;

    /**
     * @var string
     */
    public $feature;

    /**
     * @var mixed
     */
    public $scope;

    /**
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function __construct($feature, $scope)
    {
        $this->feature = $feature;

        $this->scope = $scope;
    }
}
