<?php

namespace Tests\Feature\Owner;

use App\Models\Property;
use App\Models\PropertyVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-PROP-003 / NFR-SEC-005 - walkthrough videos, and their validation.
 *
 * Videos live in property_videos, so a house and each of its floors carry their
 * own with no special casing (BR-013).
 */
class PropertyVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function video(string $name = 'walkthrough.mp4', ?int $kilobytes = null): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes ?? 5_000, 'video/mp4');
    }

    #[Test]
    public function a_video_is_uploaded_and_kept_in_its_own_table(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->post(route('owner.properties.videos.store', $property), [
                'videos' => [$this->video('tour.mp4')],
            ])
            ->assertRedirect();

        $video = $property->fresh()->videos->first();

        $this->assertNotNull($video);
        $this->assertSame('tour.mp4', $video->original_name);
        $this->assertSame('video/mp4', $video->mime_type);
        $this->assertSame(1, $video->sort_order);
        Storage::disk('public')->assertExists($video->file_path);

        // Normalised: the gallery is untouched by a video upload.
        $this->assertDatabaseCount('property_images', 0);
        $this->assertDatabaseCount('property_videos', 1);

        $this->actingAs($owner)
            ->get(route('owner.properties.show', $property))
            ->assertOk()
            ->assertSee('Videos')
            ->assertSee($video->url(), escape: false);
    }

    #[Test]
    public function a_video_over_the_size_cap_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $oversize = (int) config('rental.uploads.videos.max_kb') + 1;

        // 200 MB is the documented ceiling (SRS 9.2).
        $this->assertSame(204800, (int) config('rental.uploads.videos.max_kb'));

        $this->actingAs($owner)
            ->post(route('owner.properties.videos.store', $property), [
                'videos' => [$this->video('huge.mp4', $oversize)],
            ])
            ->assertSessionHasErrors('videos.0');

        // ...but a file just under it goes through.
        $this->actingAs($owner)
            ->post(route('owner.properties.videos.store', $property), [
                'videos' => [$this->video('just-fits.mp4', $oversize - 2)],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('property_videos', 1);
    }

    #[Test]
    public function only_video_files_are_accepted(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        // NFR-SEC-005 - an image or a document is not a video.
        $this->actingAs($owner)
            ->post(route('owner.properties.videos.store', $property), [
                'videos' => [UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('videos.0');

        $this->assertDatabaseCount('property_videos', 0);
    }

    #[Test]
    public function videos_are_capped_per_listing(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $max = (int) config('rental.uploads.videos.max_per_property');

        PropertyVideo::factory()->count($max)->for($property)->create();

        $this->actingAs($owner)
            ->post(route('owner.properties.videos.store', $property), [
                'videos' => [$this->video()],
            ])
            ->assertSessionHasErrors('videos');

        $this->assertSame($max, $property->fresh()->videos()->count());
    }

    #[Test]
    public function a_video_is_removed_with_its_file(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->post(route('owner.properties.videos.store', $property), [
            'videos' => [$this->video()],
        ]);

        $video = $property->fresh()->videos->first();
        $path = $video->file_path;

        $this->actingAs($owner)
            ->delete(route('owner.properties.videos.destroy', [$property, $video]))
            ->assertRedirect();

        $this->assertDatabaseMissing('property_videos', ['id' => $video->id]);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function a_house_and_each_of_its_floors_carry_their_own_videos(): void
    {
        $owner = User::factory()->owner()->create();
        $house = Property::factory()->splitIntoFloors()->for($owner, 'owner')->create();
        $ground = Property::factory()->floorOf($house, 'Ground Floor', 0)->for($owner, 'owner')->create();
        $first = Property::factory()->floorOf($house, 'First Floor', 1)->for($owner, 'owner')->create();

        foreach ([[$house, 'house.mp4'], [$ground, 'ground.mp4'], [$first, 'first.mp4']] as [$listing, $name]) {
            $this->actingAs($owner)->post(route('owner.properties.videos.store', $listing), [
                'videos' => [$this->video($name)],
            ])->assertRedirect();
        }

        // BR-013 - three separate listings, three separate sets of videos.
        $this->assertSame('house.mp4', $house->fresh()->videos->first()->original_name);
        $this->assertSame('ground.mp4', $ground->fresh()->videos->first()->original_name);
        $this->assertSame('first.mp4', $first->fresh()->videos->first()->original_name);

        $this->assertSame(1, $house->fresh()->videos()->count());
        $this->assertDatabaseCount('property_videos', 3);
    }

    #[Test]
    public function a_video_cannot_be_managed_through_another_listing(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $other = Property::factory()->for($owner, 'owner')->create();
        $video = PropertyVideo::factory()->for($other)->create();

        $this->actingAs($owner)
            ->delete(route('owner.properties.videos.destroy', [$property, $video]))
            ->assertNotFound();

        // BR-010 / NFR-SEC-004 - and never by another owner at all.
        $intruder = User::factory()->owner()->create();

        $this->actingAs($intruder)
            ->post(route('owner.properties.videos.store', $property), ['videos' => [$this->video()]])
            ->assertForbidden();

        $this->assertDatabaseHas('property_videos', ['id' => $video->id]);
    }

    #[Test]
    public function a_renter_can_watch_the_walkthrough_of_a_published_listing(): void
    {
        $property = Property::factory()->published()->create();
        $video = PropertyVideo::factory()->for($property)->create();

        $this->get(route('listings.show', $property))
            ->assertOk()
            ->assertSee('Video walkthrough')
            ->assertSee($video->url(), escape: false);
    }
}
