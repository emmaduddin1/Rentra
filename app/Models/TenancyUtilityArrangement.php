<?php

namespace App\Models;

use App\Enums\UtilityPaymentArrangement;
use App\Support\UtilityArrangements;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenancy_id', 'effective_from', 'electricity_arrangement', 'gas_arrangement', 'water_arrangement', 'internet_arrangement', 'other_utility_arrangement', 'changed_by', 'change_reason'])]
class TenancyUtilityArrangement extends Model
{
    protected function casts(): array
    {
        return ['effective_from' => 'date', ...array_fill_keys(UtilityArrangements::columns(), UtilityPaymentArrangement::class)];
    }

    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
