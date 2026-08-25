<?php

namespace App\Models;

use App\Enums\PaymentCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NFR-SEC-007 - proof files live on a private disk and are only reachable via
 * an authorised download route.
 */
#[Fillable([
    'monthly_record_id', 'category', 'disk', 'file_path', 'mime_type',
    'original_name', 'size_bytes', 'uploaded_by',
])]
class MonthlyProof extends Model
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => PaymentCategory::class,
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<MonthlyRecord, $this> */
    public function monthlyRecord(): BelongsTo
    {
        return $this->belongsTo(MonthlyRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
