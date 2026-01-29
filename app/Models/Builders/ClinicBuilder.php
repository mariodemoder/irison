<?php

namespace App\Models\Builders;

use App\Models\Scopes\ClinicScope;
use Illuminate\Database\Eloquent\Builder;


class ClinicBuilder extends Builder
{
    public function withoutGlobalScope($scope)
    {
        if ($scope === 'clinic' || $scope === ClinicScope::class || $scope instanceof ClinicScope) {
            return $this;
        }

        return parent::withoutGlobalScope($scope);
    }

    public function withoutGlobalScopes($scopes = null)
    {
        if (is_null($scopes)) {
            // Prevent removing all scopes (including clinic)
            return $this;
        }

        if (!is_array($scopes)) {
            if ($scopes === 'clinic' || $scopes === ClinicScope::class || $scopes instanceof ClinicScope) {
                return $this;
            }
            return parent::withoutGlobalScopes($scopes);
        }

        $filtered = array_filter($scopes, function ($s) {
            return !($s === 'clinic' || $s === ClinicScope::class || $s instanceof ClinicScope);
        });

        if (empty($filtered)) {
            return $this;
        }

        return parent::withoutGlobalScopes($filtered);
    }
}
