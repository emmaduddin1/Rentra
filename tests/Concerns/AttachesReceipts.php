<?php

namespace Tests\Concerns;

use App\Models\MonthlyProof;
use App\Models\MonthlyRecord;

/**
 * FR-MON-003 - a month cannot be submitted until every charge carries a
 * receipt, so any test that needs a *successful* submission has to put the
 * evidence in place first. This does that without going through a real upload.
 */
trait AttachesReceipts
{
    protected function attachReceipts(MonthlyRecord $record): MonthlyRecord
    {
        foreach ($record->chargedCategories() as $category) {
            MonthlyProof::factory()
                ->for($record, 'monthlyRecord')
                ->create(['category' => $category]);
        }

        return $record->load('proofs');
    }
}
