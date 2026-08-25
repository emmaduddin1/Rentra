<?php

namespace Tests\Feature\Owner;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-PROP-003 - gallery management, and NFR-SEC-005 upload validation.
 */
class PropertyImageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function images_are_appended_in_upload_order(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->post(route('owner.properties.images.store', $property), [
                'images' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                ],
            ])
            ->assertRedirect();

        $images = $property->fresh()->images;

        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());
        $this->assertSame('one.jpg', $images->first()->original_name);
        $this->assertTrue($images->first()->is_primary);
    }

    #[Test]
    public function uploads_are_validated_by_type_and_size(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        // NFR-SEC-005 - a document is not an image, whatever it is called.
        $this->actingAs($owner)
            ->post(route('owner.properties.images.store', $property), [
                'images' => [UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('images.0');

        $oversize = config('rental.uploads.images.max_kb') + 1;

        $this->actingAs($owner)
            ->post(route('owner.properties.images.store', $property), [
                'images' => [UploadedFile::fake()->image('huge.jpg')->size($oversize)],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('property_images', 0);
    }

    #[Test]
    public function the_gallery_is_capped_per_property(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $max = config('rental.uploads.images.max_per_property');

        PropertyImage::factory()->count($max)->for($property)->create();

        $this->actingAs($owner)
            ->post(route('owner.properties.images.store', $property), [
                'images' => [UploadedFile::fake()->image('extra.jpg')],
            ])
            ->assertSessionHasErrors('images');

        $this->assertSame($max, $property->fresh()->images()->count());
    }

    #[Test]
    public function the_primary_image_can_be_changed(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $first = PropertyImage::factory()->primary()->for($property)->create(['sort_order' => 1]);
        $second = PropertyImage::factory()->for($property)->create(['sort_order' => 2]);

        $this->actingAs($owner)
            ->patch(route('owner.properties.images.primary', [$property, $second]))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame($second->id, $property->fresh()->primaryImage->id);
    }

    #[Test]
    public function deleting_the_primary_image_promotes_the_next_one(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        // One above the minimum, so the gallery may lose an image at all.
        $this->actingAs($owner)->post(route('owner.properties.images.store', $property), [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
                UploadedFile::fake()->image('four.jpg'),
            ],
        ]);

        $property->refresh();
        $primary = $property->images->first();
        $second = $property->images->get(1);
        $path = $primary->file_path;

        $this->actingAs($owner)
            ->delete(route('owner.properties.images.destroy', [$property, $primary]))
            ->assertRedirect();

        $this->assertDatabaseMissing('property_images', ['id' => $primary->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertTrue($second->fresh()->is_primary);
    }

    #[Test]
    public function the_gallery_cannot_be_emptied_below_the_minimum(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $min = config('rental.uploads.images.min_per_property');

        $images = PropertyImage::factory()->count($min)->for($property)->create();

        // FR-PROP-003 - removing one would leave the listing short.
        $this->actingAs($owner)
            ->delete(route('owner.properties.images.destroy', [$property, $images->first()]))
            ->assertSessionHasErrors('images');

        $this->assertSame($min, $property->fresh()->images()->count());
    }

    #[Test]
    public function an_image_cannot_be_managed_through_another_property(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $other = Property::factory()->for($owner, 'owner')->create();
        $image = PropertyImage::factory()->for($other)->create();

        $this->actingAs($owner)
            ->delete(route('owner.properties.images.destroy', [$property, $image]))
            ->assertNotFound();

        $this->assertDatabaseHas('property_images', ['id' => $image->id]);
    }

    #[Test]
    public function another_owner_cannot_touch_the_gallery(): void
    {
        Storage::fake('public');
        $property = Property::factory()->create();
        $image = PropertyImage::factory()->for($property)->create();
        $intruder = User::factory()->owner()->create();

        // BR-010 / NFR-SEC-004.
        $this->actingAs($intruder)
            ->post(route('owner.properties.images.store', $property), [
                'images' => [UploadedFile::fake()->image('sneaky.jpg')],
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('owner.properties.images.destroy', [$property, $image]))
            ->assertForbidden();
    }
}
