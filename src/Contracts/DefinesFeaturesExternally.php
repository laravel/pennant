<?php

namespace Laravel\Pennant\Contracts;

interface DefinesFeaturesExternally
{
    /**
     * Retrieve the defined features for the given scope.
     *
     * @return list<string>
     */
    public function definedFeaturesForScope(mixed $scope): array;
}
