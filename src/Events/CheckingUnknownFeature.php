<?php

namespace Laravel\Feature\Events;

use Illuminate\Queue\SerializesModels;

class CheckingUnknownFeature
{
    use SerializesModels;

    /**
     * The feature name.
     *
     * @var string
     */
    public $feature;

    /**
     * The scope of the feature check.
     *
     * @var \Illuminate\Support\Collection<int, mixed>
     */
    public $scope;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature
     * @param  \Illuminate\Support\Collection<int, mixed>  $scope
     */
    public function __construct($feature, $scope)
    {
        $this->feature = $feature;

        $this->scope = $scope;
    }
}
