<?php

namespace Tests\Feature\Api;

use App\Enums\MaintenanceStatus;
use App\Enums\MonthlyRecordStatus;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AttachesReceipts;
use Tests\TestCase;

/**
 * SRS 9.3 - the API drives the same workflows as the portal, through the same
 * actions and policies (NFR-MNT-001).
 */
class ApiWorkflowTest extends TestCase
{
    use AttachesReceipts;
    use RefreshDatabase;

    #[Test]
    public function the_public_feed_shows_only_visible_listings_and_hides_contact_from_guests(): void
    {
        $visible = Property::factory()->published()->create([
            'title' => 'Published listing',
            'address' => '12 Main Boulevard',
            'contact_phone' => '+923001112233',
        ]);
        Property::factory()->create(['title' => 'Draft listing']);
        Property::factory()->published()->occupied()->create(['title' => 'Occupied listing']);

        // BR-001.
        $response = $this->getJson(route('api.v1.listings.index'))->assertOk();

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Published listing', $titles);
        $this->assertNotContains('Draft listing', $titles);
        $this->assertNotContains('Occupied listing', $titles);

        // SRS 19 - the address and contact stay hidden until you sign in.
        $guestView = $this->getJson(route('api.v1.listings.show', $visible))->assertOk();
        $this->assertNull($guestView->json('data.address'));
        $this->assertTrue($guestView->json('data.contact_requires_login'));

        Sanctum::actingAs(User::factory()->renter()->create());

        $memberView = $this->getJson(route('api.v1.listings.show', $visible))->assertOk();
        $this->assertSame('12 Main Boulevard', $memberView->json('data.address'));
        $this->assertSame('+923001112233', $memberView->json('data.contact.phone'));
    }

    #[Test]
    public function the_feed_searches_by_area(): void
    {
        // FR-APP-002.
        Property::factory()->published()->create(['title' => 'Gulberg house', 'area' => 'Gulberg']);
        Property::factory()->published()->create(['title' => 'Clifton flat', 'area' => 'Clifton']);

        $titles = collect(
            $this->getJson(route('api.v1.listings.index', ['area' => 'Gulberg']))->assertOk()->json('data')
        )->pluck('title');

        $this->assertContains('Gulberg house', $titles);
        $this->assertNotContains('Clifton flat', $titles);
    }

    #[Test]
    public function a_hidden_listing_is_not_found_through_the_api(): void
    {
        $draft = Property::factory()->create();

        // SRS 12 - existence is not disclosed.
        $this->getJson(route('api.v1.listings.show', $draft))->assertNotFound();
    }

    #[Test]
    public function an_owner_creates_and_lists_properties(): void
    {
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        Storage::fake('public');

        // FR-PROP-003 - the API answers to the same gallery minimum as the form.
        $this->post(route('api.v1.properties.store'), [
            'type' => 'house',
            'title' => 'API created house',
            'rent_amount' => '65000.00',
            'area' => 'Gulberg',
            'address' => '5 Side Street',
            'status' => 'available',
            'publication_status' => 'published',
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('properties', [
            'title' => 'API created house',
            'owner_id' => $owner->id,
        ]);

        $listed = $this->getJson(route('api.v1.properties.index'))->assertOk();
        $this->assertCount(1, $listed->json('data'));
        // BR-012 - money is a decimal string, not a float.
        $this->assertSame('65000.00', $listed->json('data.0.rent_amount'));
    }

    #[Test]
    public function property_writes_are_owner_only_and_scoped(): void
    {
        $property = Property::factory()->create();

        // BR-010 - another owner cannot read or write it.
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson(route('api.v1.properties.show', $property))->assertForbidden();

        // The role middleware keeps renters out entirely.
        Sanctum::actingAs(User::factory()->renter()->create());
        $this->getJson(route('api.v1.properties.index'))->assertForbidden();
        $this->postJson(route('api.v1.properties.store'), [])->assertForbidden();
    }

    #[Test]
    public function the_full_monthly_cycle_works_over_the_api(): void
    {
        $tenancy = Tenancy::factory()->create();
        $renter = $tenancy->renter;
        $owner = $tenancy->property->owner;

        // FR-MON-001 - the renter opens the record.
        Sanctum::actingAs($renter);

        $created = $this->postJson(route('api.v1.monthly-records.store'), [
            'tenancy_id' => $tenancy->id,
            'period_month' => now()->format('Y-m'),
            'rent_amount' => '50000.00',
            'electricity_amount' => '4000.25',
        ])->assertCreated();

        $recordId = $created->json('data.id');
        $this->assertSame('54000.25', $created->json('data.total_amount'));
        $this->assertSame('draft', $created->json('data.status'));

        // BR-003 - the same month twice is refused.
        $this->postJson(route('api.v1.monthly-records.store'), [
            'tenancy_id' => $tenancy->id,
            'period_month' => now()->format('Y-m'),
            'rent_amount' => '50000.00',
        ])->assertStatus(422)->assertJsonValidationErrors('period_month');

        // FR-MON-005 - submitting locks it, once every charge is evidenced.
        $record = MonthlyRecord::findOrFail($recordId);
        $this->attachReceipts($record);
        $this->postJson(route('api.v1.monthly-records.submit', $record))->assertOk();
        $this->assertSame(MonthlyRecordStatus::Pending, $record->fresh()->status);

        // BR-004 - the renter can no longer edit it.
        $this->patchJson(route('api.v1.monthly-records.update', $record), ['rent_amount' => '1.00'])
            ->assertForbidden();

        // FR-MON-006/008 - the owner approves, and it freezes.
        Sanctum::actingAs($owner);

        $approved = $this->postJson(route('api.v1.monthly-records.approve', $record))->assertOk();
        $this->assertTrue($approved->json('data.is_frozen'));
        $this->assertSame('Frozen', $approved->json('data.status_label'));

        // FR-MON-009 - reopening needs a reason.
        $this->postJson(route('api.v1.monthly-records.reopen', $record), [])
            ->assertStatus(422)->assertJsonValidationErrors('reopen_reason');

        $reopened = $this->postJson(route('api.v1.monthly-records.reopen', $record), [
            'reopen_reason' => 'The water bill belongs to the next month.',
        ])->assertOk();

        $this->assertFalse($reopened->json('data.is_frozen'));
        $this->assertSame('pending', $reopened->json('data.status'));

        // FR-MON-007 - rejection needs a reason and hands it back.
        $this->postJson(route('api.v1.monthly-records.reject', $record), [
            'rejection_reason' => 'Please attach the electricity bill.',
        ])->assertOk();

        $this->assertSame(MonthlyRecordStatus::Rejected, $record->fresh()->status);
    }

    #[Test]
    public function approval_endpoints_are_closed_to_renters(): void
    {
        $record = MonthlyRecord::factory()->pending()->create();

        Sanctum::actingAs($record->tenancy->renter);

        // The role middleware answers before the policy does.
        $this->postJson(route('api.v1.monthly-records.approve', $record))->assertForbidden();
        $this->postJson(route('api.v1.monthly-records.reject', $record), [
            'rejection_reason' => 'Approving my own submission.',
        ])->assertForbidden();

        $this->assertSame(MonthlyRecordStatus::Pending, $record->fresh()->status);
    }

    #[Test]
    public function monthly_records_are_scoped_by_role(): void
    {
        // BR-010.
        $mine = MonthlyRecord::factory()->pending()->create();
        $theirs = MonthlyRecord::factory()->pending()->create();

        Sanctum::actingAs($mine->tenancy->renter);
        $ids = collect($this->getJson(route('api.v1.monthly-records.index'))->json('data'))->pluck('id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);

        Sanctum::actingAs($mine->tenancy->property->owner);
        $ids = collect($this->getJson(route('api.v1.monthly-records.index'))->json('data'))->pluck('id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);

        $this->getJson(route('api.v1.monthly-records.show', $theirs))->assertForbidden();
    }

    #[Test]
    public function a_tenancy_is_assigned_and_ended_over_the_api(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->for($owner, 'owner')->create();
        $renter = User::factory()->renter()->create();

        Sanctum::actingAs($owner);

        $created = $this->postJson(route('api.v1.tenancies.store'), [
            'property_id' => $property->id,
            'renter_mode' => 'existing',
            'renter_id' => $renter->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_rent' => '70000.00',
        ])->assertCreated();

        $tenancy = Tenancy::findOrFail($created->json('data.id'));
        $this->assertSame('occupied', $property->fresh()->status->value);

        $identifierProperty = Property::factory()->for($owner, 'owner')->create();
        $identifierRenter = User::factory()->renter()->create(['email' => 'linked@example.test']);

        $linked = $this->postJson(route('api.v1.tenancies.store'), [
            'property_id' => $identifierProperty->id,
            'renter_mode' => 'existing',
            'renter_identifier' => 'linked@example.test',
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_rent' => '71000.00',
        ])->assertCreated();

        $this->assertSame($identifierRenter->id, $linked->json('data.renter.id'));

        // BR-002 - a second active tenancy is refused.
        $this->postJson(route('api.v1.tenancies.store'), [
            'property_id' => $property->id,
            'renter_mode' => 'existing',
            'renter_id' => User::factory()->renter()->create()->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'agreed_rent' => '70000.00',
        ])->assertForbidden();

        // FR-TEN-005.
        $this->patchJson(route('api.v1.tenancies.end', $tenancy), [
            'end_date' => now()->toDateString(),
            'property_status' => 'available',
        ])->assertOk();

        $this->assertSame('ended', $tenancy->fresh()->status->value);
        $this->assertSame('available', $property->fresh()->status->value);
    }

    #[Test]
    public function the_maintenance_lifecycle_works_over_the_api(): void
    {
        $tenancy = Tenancy::factory()->create();

        Sanctum::actingAs($tenancy->renter);

        $created = $this->postJson(route('api.v1.maintenance-requests.store'), [
            'tenancy_id' => $tenancy->id,
            'title' => 'Bedroom AC not cooling',
            'description' => 'The unit runs but blows warm air all day.',
            'priority' => 'high',
        ])->assertCreated();

        $request = MaintenanceRequest::findOrFail($created->json('data.id'));
        $this->assertSame(['in_progress', 'completed', 'cancelled'], $created->json('data.allowed_transitions'));

        // FR-MNT-003 - the renter cannot move the status.
        $this->patchJson(route('api.v1.maintenance-requests.status', $request), ['status' => 'completed'])
            ->assertForbidden();

        Sanctum::actingAs($tenancy->property->owner);

        $this->patchJson(route('api.v1.maintenance-requests.status', $request), [
            'status' => 'in_progress',
            'comment' => 'Technician booked.',
        ])->assertOk();

        // SRS 12 - an illegal transition is refused.
        $this->patchJson(route('api.v1.maintenance-requests.status', $request), ['status' => 'pending'])
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->assertSame(MaintenanceStatus::InProgress, $request->fresh()->status);
    }

    #[Test]
    public function a_renter_applies_and_the_owner_responds_over_the_api(): void
    {
        $property = Property::factory()->published()->create();
        $renter = User::factory()->renter()->create();

        Sanctum::actingAs($renter);

        $created = $this->postJson(route('api.v1.applications.store', $property), [
            'message' => 'I would like to view this property this weekend.',
        ])->assertCreated();

        // FR-APP-005 - the owner is notified.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $property->owner_id,
            'type' => 'application.submitted',
        ]);

        // A duplicate open inquiry is refused with a clear message.
        $this->postJson(route('api.v1.applications.store', $property), [
            'message' => 'Asking a second time about this property.',
        ])->assertStatus(422)->assertJsonValidationErrors('message');

        Sanctum::actingAs($property->owner);

        $this->patchJson(route('api.v1.applications.update', $created->json('data.id')), [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertDatabaseHas('applications', [
            'id' => $created->json('data.id'),
            'status' => 'accepted',
        ]);
    }

    #[Test]
    public function notifications_are_listed_and_marked_read_over_the_api(): void
    {
        // FR-NOT-003.
        $record = $this->attachReceipts(MonthlyRecord::factory()->create());
        $renter = $record->tenancy->renter;

        Sanctum::actingAs($renter);
        $this->postJson(route('api.v1.monthly-records.submit', $record))->assertOk();

        Sanctum::actingAs($record->tenancy->property->owner);

        $listed = $this->getJson(route('api.v1.notifications.index'))->assertOk();
        $this->assertSame(1, $listed->json('meta.unread_count'));
        $this->assertSame('monthly_record.submitted', $listed->json('data.0.type'));

        $this->postJson(route('api.v1.notifications.read'))->assertOk()->assertJson(['unread_count' => 0]);
    }

    #[Test]
    public function a_notification_belonging_to_someone_else_cannot_be_marked_read(): void
    {
        $notification = app(Notifier::class)->send(
            recipient: User::factory()->renter()->create(),
            type: 'test.event',
            title: 'Theirs',
        );

        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson(route('api.v1.notifications.read'), ['ids' => [$notification->id]])
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }
}
