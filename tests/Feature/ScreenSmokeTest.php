<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Favorite;
use App\Models\MaintenanceRequest;
use App\Models\MonthlyRecord;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS 11 - every screen renders for the role that owns it.
 *
 * Cheap insurance: a broken Blade component or a missing view variable fails
 * here instead of in the browser.
 */
class ScreenSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_owner_screens_render(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->published()->for($owner, 'owner')->create();
        PropertyImage::factory()->primary()->for($property)->create();
        $application = Application::factory()->for($property, 'property')->create();

        $occupied = Property::factory()->occupied()->for($owner, 'owner')->create();
        $tenancy = Tenancy::factory()->for($occupied, 'property')->create();
        $record = MonthlyRecord::factory()->for($tenancy)->pending()->create();
        $maintenance = MaintenanceRequest::factory()->for($tenancy)->create();

        app(Notifier::class)->send($owner, 'test.event', 'A test notification');

        $screens = [
            route('owner.dashboard'),
            route('owner.properties.index'),
            route('owner.properties.index', ['archived' => 1]),
            route('owner.properties.create'),
            route('owner.properties.show', $property),
            route('owner.properties.edit', $property),
            route('owner.tenancies.index'),
            route('owner.tenancies.create'),
            route('owner.tenancies.show', $tenancy),
            route('owner.tenancies.edit', $tenancy),
            route('owner.renters.index'),
            route('owner.renters.show', $tenancy->renter),
            route('owner.monthly-records.index'),
            route('owner.monthly-records.index', ['status' => 'approved']),
            route('owner.monthly-records.show', $record),
            route('owner.maintenance.index'),
            route('owner.maintenance.show', $maintenance),
            route('owner.applications.index'),
            route('owner.applications.show', $application),
            route('owner.reports.index'),
            route('owner.audit-logs.index'),
            route('notifications.index'),
            route('notifications.index', ['filter' => 'unread']),
            route('profile.edit'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($owner)->get($screen)->assertOk();
        }
    }

    #[Test]
    public function the_renter_screens_render(): void
    {
        $tenancy = Tenancy::factory()->create();
        $renter = $tenancy->renter;
        $draft = MonthlyRecord::factory()->for($tenancy)->create();
        $approved = MonthlyRecord::factory()->for($tenancy)->approved()->create([
            'period_month' => now()->subMonth()->startOfMonth()->toDateString(),
        ]);
        $maintenance = MaintenanceRequest::factory()->for($tenancy)->create();

        app(Notifier::class)->send($renter, 'test.event', 'A test notification');

        $screens = [
            route('renter.dashboard'),
            route('renter.tenancies.index'),
            route('renter.tenancies.show', $tenancy),
            route('renter.monthly-records.index'),
            route('renter.monthly-records.create'),
            route('renter.monthly-records.show', $draft),
            route('renter.monthly-records.edit', $draft),
            // BR-006 - a frozen record still opens, read-only.
            route('renter.monthly-records.show', $approved),
            route('renter.maintenance.index'),
            route('renter.maintenance.create'),
            route('renter.maintenance.show', $maintenance),
            route('notifications.index'),
            route('profile.edit'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($renter)->get($screen)->assertOk();
        }
    }

    #[Test]
    public function the_renter_search_screens_render(): void
    {
        $renter = User::factory()->renter()->create();
        $property = Property::factory()->published()->create();
        PropertyImage::factory()->primary()->for($property)->create();
        Favorite::factory()->create(['renter_id' => $renter->id, 'property_id' => $property->id]);
        Application::factory()->create(['renter_id' => $renter->id, 'property_id' => $property->id]);

        $screens = [
            route('renter.dashboard'),
            route('renter.applications.index'),
            route('renter.favorites.index'),
            route('listings.index'),
            route('listings.show', $property),
            route('notifications.index'),
            route('profile.edit'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($renter)->get($screen)->assertOk();
        }
    }

    #[Test]
    public function the_public_screens_render_for_a_guest(): void
    {
        $property = Property::factory()->published()->create();
        PropertyImage::factory()->primary()->for($property)->create();

        $this->get(route('home'))->assertOk();
        $this->get(route('listings.index'))->assertOk();
        $this->get(route('listings.show', $property))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }
}
