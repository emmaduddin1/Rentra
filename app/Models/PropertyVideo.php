<?php

namespace App\Models;

use Database\Factories\PropertyVideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'property_id', 'disk', 'file_path', 'original_name', 'mime_type',
    'size_bytes', 'sort_order',
])]
class PropertyVideo extends Model
{
    /** @use HasFactory<PropertyVideoFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Listing videos are public assets (SRS 9.2), like the gallery images.
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * Rounded to one decimal, so the owner sees "142.3 MB" and not the raw byte
     * count when deciding what to remove.
     */
    public function sizeInMegabytes(): float
    {
        return round((int) $this->size_bytes / 1048576, 1);
    }
}
