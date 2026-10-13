<?php

namespace App\Models;

use App\Enums\AreaUnit;
use App\Enums\FurnishingStatus;
use App\Enums\ListingPurpose;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\PublicationStatus;
use App\Enums\RentalMode;
use App\Enums\TenancyStatus;
use App\Support\PropertyPrice;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'parent_id', 'type', 'category_id', 'listing_purpose', 'rental_mode',
    'title', 'unit_label', 'floor_number', 'total_floors',
    'rent_amount', 'sale_price', 'area', 'area_value', 'area_unit', 'city', 'address', 'description',
    'bedrooms', 'bathrooms', 'total_rooms', 'kitchens', 'parking_spaces', 'furnishing_status',
    'contact_name', 'contact_phone', 'contact_email', 'latitude', 'longitude', 'map_address',
    'status', 'publication_status', 'published_at',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The building-level fields a floor always shares with its house.
     *
     * A floor sits inside its house, so its type and address cannot diverge;
     * they are copied down on create and kept in sync on update.
     *
     * @var list<string>
     */
    public const INHERITED_FROM_HOUSE = [
        'type', 'category_id', 'listing_purpose', 'area', 'city', 'address', 'contact_name', 'contact_phone', 'contact_email',
        'latitude', 'longitude', 'map_address',
    ];

    /**
     * Mirrors the column default, so a listing is an independent unit before it
     * has ever been saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rental_mode' => RentalMode::Whole->value,
        'listing_purpose' => ListingPurpose::Rent->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'listing_purpose' => ListingPurpose::class,
            'rental_mode' => RentalMode::class,
            'floor_number' => 'integer',
            'total_floors' => 'integer',
            'status' => PropertyStatus::class,
            'publication_status' => PublicationStatus::class,
            'rent_amount' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'area_value' => 'decimal:2',
            'area_unit' => AreaUnit::class,
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'total_rooms' => 'integer',
            'kitchens' => 'integer',
            'parking_spaces' => 'integer',
            'furnishing_status' => FurnishingStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'published_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<PropertyCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    /** @return BelongsToMany<Amenity, $this> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->ordered()->withTimestamps();
    }

    /**
     * The house this floor belongs to; null on a top-level property.
     *
     * @return BelongsTo<Property, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'parent_id');
    }

    /**
     * FR-PROP-001 - the separately rentable floors of this house.
     *
     * @return HasMany<Property, $this>
     */
    public function floors(): HasMany
    {
        return $this->hasMany(Property::class, 'parent_id')
            ->orderBy('floor_number')
            ->orderBy('id');
    }

    /** @return HasMany<PropertyImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasOne<PropertyImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * FR-PROP-003 - walkthrough clips, kept apart from the gallery.
     *
     * @return HasMany<PropertyVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(PropertyVideo::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Tenancy, $this> */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }

    /**
     * BR-002 - at most one active tenancy per property.
     *
     * @return HasOne<Tenancy, $this>
     */
    public function activeTenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class)->where('status', TenancyStatus::Active->value);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    /**
     * BR-001 - only Published + Available properties are visible to renters,
     * and BR-013 keeps a house that is split into floors out of the feed.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->isRentable()
            && $this->publication_status === PublicationStatus::Published
            && $this->status === PropertyStatus::Available;
    }

    /**
     * BR-013 - a house rented floor by floor is a container: it is published,
     * applied for, and rented through its floors, never on its own.
     */
    public function isRentable(): bool
    {
        return ($this->rental_mode ?? RentalMode::Whole)->isRentable();
    }

    public function isSplitIntoFloors(): bool
    {
        return $this->rental_mode === RentalMode::ByFloor;
    }

    public function isFloor(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * The stored title of a floor, so every existing screen that prints
     * $property->title reads "Gulberg Villa - Ground Floor" unchanged.
     */
    public static function composeFloorTitle(self $house, string $unitLabel): string
    {
        return trim($house->title).' - '.trim($unitLabel);
    }

    public function isOccupied(): bool
    {
        return $this->status === PropertyStatus::Occupied;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function isForSale(): bool
    {
        return $this->listing_purpose === ListingPurpose::Sale;
    }

    public function isForRent(): bool
    {
        return ! $this->isForSale();
    }

    public function priceAmount(): string
    {
        return PropertyPrice::for($this)->amount;
    }

    public function priceSuffix(): ?string
    {
        return PropertyPrice::for($this)->suffix;
    }

    public function price(): PropertyPrice
    {
        return PropertyPrice::for($this);
    }

    public function purposeBadgeLabel(): string
    {
        return ($this->listing_purpose ?? ListingPurpose::Rent)->badgeLabel();
    }

    public function categoryLabel(): string
    {
        return $this->category?->name ?? $this->type->label();
    }

    public function categoryGroupLabel(): ?string
    {
        return $this->category?->group?->label();
    }

    public function formattedAreaValue(): ?string
    {
        if ($this->area_value === null || $this->area_unit === null) {
            return null;
        }

        $value = rtrim(rtrim(number_format((float) $this->area_value, 2), '0'), '.');

        return $value.' '.$this->area_unit->shortLabel();
    }

    /**
     * @return array<string, string>
     */
    public function visibleSpecifications(): array
    {
        return collect([
            'bedrooms' => $this->bedrooms !== null ? trans_choice(':count bed|:count beds', $this->bedrooms, ['count' => $this->bedrooms]) : null,
            'bathrooms' => $this->bathrooms !== null ? trans_choice(':count bath|:count baths', $this->bathrooms, ['count' => $this->bathrooms]) : null,
            'total_rooms' => $this->total_rooms !== null ? trans_choice(':count room|:count rooms', $this->total_rooms, ['count' => $this->total_rooms]) : null,
            'kitchens' => $this->kitchens !== null ? trans_choice(':count kitchen|:count kitchens', $this->kitchens, ['count' => $this->kitchens]) : null,
            'parking_spaces' => $this->parking_spaces !== null ? trans_choice(':count parking|:count parking spaces', $this->parking_spaces, ['count' => $this->parking_spaces]) : null,
            'floor_number' => $this->floor_number !== null ? __('Floor :number', ['number' => $this->floor_number]) : null,
            'total_floors' => $this->total_floors !== null ? trans_choice(':count floor total|:count floors total', $this->total_floors, ['count' => $this->total_floors]) : null,
            'furnishing_status' => $this->furnishing_status?->label(),
            'area' => $this->formattedAreaValue(),
        ])
            ->filter()
            ->only($this->relevantSpecificationKeys())
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function relevantSpecificationKeys(): array
    {
        $slug = $this->category?->slug;

        return match ($slug) {
            'shop', 'warehouse', 'factory', 'showroom' => [
                'total_rooms', 'parking_spaces', 'floor_number', 'total_floors', 'area',
            ],
            'office', 'co-working-space', 'commercial-building' => [
                'bathrooms', 'total_rooms', 'kitchens', 'parking_spaces', 'floor_number', 'total_floors', 'furnishing_status', 'area',
            ],
            default => [
                'bedrooms', 'bathrooms', 'total_rooms', 'kitchens', 'parking_spaces', 'floor_number', 'total_floors', 'furnishing_status', 'area',
            ],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * BR-001 - the public listing feed.
     *
     * @param  Builder<static>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->rentable()
            ->where('publication_status', PublicationStatus::Published->value)
            ->where('status', PropertyStatus::Available->value);
    }

    /**
     * BR-013 - everything a renter or a tenancy may point at: independent
     * properties and individual floors, but not the houses that hold floors.
     *
     * @param  Builder<static>  $query
     */
    public function scopeRentable(Builder $query): void
    {
        $query->where('rental_mode', RentalMode::Whole->value);
    }

    /**
     * Houses and independent properties, without the floors nested under them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * BR-010 - an owner may only reach their own portfolio.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, User|int $owner): void
    {
        $query->where('owner_id', $owner instanceof User ? $owner->id : $owner);
    }

    /**
     * FR-APP-002 - area search.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInArea(Builder $query, ?string $area): void
    {
        $area = trim((string) $area);

        if ($area === '') {
            return;
        }

        $query->where(function (Builder $query) use ($area) {
            $query->where('area', 'like', "%{$area}%")
                ->orWhere('city', 'like', "%{$area}%");
        });
    }

    /** @param Builder<static> $query */
    public function scopePurpose(Builder $query, ListingPurpose|string|null $purpose): void
    {
        if ($purpose === null || $purpose === '') {
            return;
        }

        $query->where('listing_purpose', $purpose instanceof ListingPurpose ? $purpose->value : $purpose);
    }
}
