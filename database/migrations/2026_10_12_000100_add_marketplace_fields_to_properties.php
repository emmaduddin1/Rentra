<?php

use App\Enums\AreaUnit;
use App\Enums\ListingPurpose;
use App\Support\PhaseOneDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon_identifier')->default('check');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
        });

        Schema::create('amenity_property', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['amenity_id', 'property_id']);
        });

        foreach (PhaseOneDefaults::categories() as $category) {
            DB::table('property_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'group' => $category['group'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ],
            );
        }

        foreach (PhaseOneDefaults::amenities() as $amenity) {
            DB::table('amenities')->updateOrInsert(
                ['slug' => $amenity['slug']],
                [
                    'name' => $amenity['name'],
                    'icon_identifier' => $amenity['icon_identifier'] ?? 'check',
                    'is_active' => true,
                    'sort_order' => $amenity['sort_order'],
                ],
            );
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->string('listing_purpose')
                ->default(ListingPurpose::Rent->value)
                ->after('rental_mode')
                ->index();
            $table->foreignId('category_id')
                ->nullable()
                ->after('type')
                ->constrained('property_categories')
                ->nullOnDelete();
            $table->decimal('sale_price', 14, 2)->nullable()->after('rent_amount');

            $table->decimal('area_value', 12, 2)->nullable()->after('area');
            $table->string('area_unit')->nullable()->after('area_value');

            $table->unsignedSmallInteger('bedrooms')->nullable()->after('description');
            $table->unsignedSmallInteger('bathrooms')->nullable()->after('bedrooms');
            $table->unsignedSmallInteger('total_rooms')->nullable()->after('bathrooms');
            $table->unsignedSmallInteger('kitchens')->nullable()->after('total_rooms');
            $table->unsignedSmallInteger('parking_spaces')->nullable()->after('kitchens');
            $table->unsignedSmallInteger('total_floors')->nullable()->after('floor_number');
            $table->string('furnishing_status')->nullable()->after('total_floors');

            $table->decimal('latitude', 10, 7)->nullable()->after('contact_email');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('map_address')->nullable()->after('longitude');

            $table->index(['category_id', 'listing_purpose']);
            $table->index(['area_unit', 'area_value']);
            $table->index(['latitude', 'longitude']);
        });

        $categoryIds = DB::table('property_categories')->pluck('id', 'slug');

        foreach (DB::table('properties')->select(['id', 'type'])->orderBy('id')->cursor() as $property) {
            $slug = PhaseOneDefaults::categorySlugForType($property->type);

            DB::table('properties')
                ->where('id', $property->id)
                ->update([
                    'listing_purpose' => ListingPurpose::Rent->value,
                    'category_id' => $categoryIds[$slug] ?? null,
                    'area_unit' => AreaUnit::SquareFeet->value,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'listing_purpose']);
            $table->dropIndex(['area_unit', 'area_value']);
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'listing_purpose',
                'category_id',
                'sale_price',
                'area_value',
                'area_unit',
                'bedrooms',
                'bathrooms',
                'total_rooms',
                'kitchens',
                'parking_spaces',
                'total_floors',
                'furnishing_status',
                'latitude',
                'longitude',
                'map_address',
            ]);
        });

        Schema::dropIfExists('amenity_property');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('property_categories');
    }
};
