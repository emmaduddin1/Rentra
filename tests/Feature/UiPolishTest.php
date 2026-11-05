<?php

namespace Tests\Feature;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_filters_are_collapsed_until_needed(): void
    {
        $response = $this->get('/listings')->assertOk();
        $response->assertSee('aria-expanded="false"', false)->assertSee('listing-extra-filters');
        $response->assertDontSee('Clear filters');
    }

    public function test_advanced_filters_expand_and_preserve_zero_and_legacy_building_type(): void
    {
        $response = $this->get('/listings?min_area=0&area_unit='.AreaUnit::SquareFeet->value.'&type='.PropertyType::House->value)->assertOk();
        $response->assertSee('aria-expanded="true"', false)->assertSee('3 active');
        $response->assertSee('value="0"', false)->assertSee('Clear filters');
        $this->assertSame('0', $response->viewData('filters')['min_area']);
        $this->assertSame(PropertyType::House->value, $response->viewData('filters')['type']);
    }

    public function test_secondary_filter_errors_expand_the_section_even_without_a_value(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['max_area' => 'Maximum size must exceed minimum size.']));
        $this->withSession(['errors' => $errors]);
        $session = $this->app['session']->driver();
        $session->save();
        $this->withCookie($session->getName(), $session->getId())->get('/listings')->assertOk()->assertSessionHasErrors('max_area')
            ->assertSee('aria-expanded="true"', false)->assertSee('Maximum size must exceed minimum size.');
    }

    public function test_search_pagination_keeps_all_query_filters(): void
    {
        config(['rental.listings.per_page' => 2]);
        Property::factory()->published()->count(3)->create([
            'area' => 'Gulberg', 'type' => PropertyType::House, 'rent_amount' => 75000,
            'area_value' => 1000, 'area_unit' => AreaUnit::SquareFeet,
        ]);
        $filters = ['area' => 'Gulberg', 'type' => PropertyType::House->value, 'min_rent' => '0',
            'max_rent' => '100000', 'min_area' => '0', 'area_unit' => AreaUnit::SquareFeet->value];
        $response = $this->get('/listings?'.http_build_query($filters))->assertOk();
        parse_str(parse_url($response->viewData('listings')->nextPageUrl(), PHP_URL_QUERY), $next);
        foreach ($filters as $key => $value) {
            $this->assertSame($value, $next[$key]);
        }
        $this->assertSame('2', $next['page']);
    }

    public function test_inbox_and_preferences_remain_accessible_when_desktop_alerts_are_unavailable(): void
    {
        config(['push.enabled' => false]);
        $user = User::factory()->renter()->create();
        $this->actingAs($user)->get('/notifications')->assertOk()
            ->assertSeeInOrder(['Your inbox', 'Notification settings', 'Topics you follow'])
            ->assertSee('data-push-enable hidden', false)->assertSee('data-push-disable hidden', false)
            ->assertSee('data-push-test hidden', false)->assertSee('Save preferences');
        $this->actingAs($user)->patchJson('/notification-preferences', ['payments' => false])->assertOk();
        $this->assertDatabaseHas('web_notification_preferences', ['user_id' => $user->id, 'payments' => false]);
    }

    public function test_notification_pages_match_the_live_feed_and_preserve_the_unread_filter(): void
    {
        $user = User::factory()->renter()->create();
        $user->inAppNotifications()->createMany(array_fill(0, 45, ['type' => 'account.test', 'title' => 'Unread update']));
        $user->inAppNotifications()->create(['type' => 'account.test', 'title' => 'Read update', 'read_at' => now()]);

        foreach ([1, 2, 3] as $number) {
            $response = $this->actingAs($user)->get('/notifications?filter=unread&page='.$number)->assertOk();
            $paginator = $response->viewData('notifications');
            $this->assertSame(20, $paginator->perPage());
            $this->assertSame(3, $paginator->lastPage());
            $response->assertSee('aria-label="Notification pages"', false)
                ->assertSee('aria-current="page" aria-label="Page '.$number.'"', false)
                ->assertSeeInOrder(['Previous', 'Go to page '.($number === 1 ? 2 : 1), 'Next']);
            $response->assertSee('filter=unread&amp;page='.($number === 1 ? 2 : 1), false);

            $feed = $this->getJson('/notifications/feed?filter=unread&page='.$number)->assertOk()
                ->assertJsonPath('page', $number)->assertJsonPath('last_page', 3);
            $this->assertSame($paginator->pluck('id')->map(fn ($id) => (string) $id)->all(), array_column($feed->json('notifications'), 'id'));
        }
    }

    public function test_notification_pagination_has_gaps_for_large_inboxes_and_is_hidden_for_one_page(): void
    {
        $user = User::factory()->renter()->create();
        $this->actingAs($user)->get('/notifications')->assertOk()->assertDontSee('aria-label="Notification pages"', false);

        $user->inAppNotifications()->createMany(array_fill(0, 221, ['type' => 'account.test', 'title' => 'Inbox update']));
        $this->get('/notifications?page=6')->assertOk()
            ->assertSeeInOrder(['Previous', 'Go to page 1', '…', 'Go to page 5', 'Page 6', 'Go to page 7', '…', 'Go to page 12', 'Next']);
    }
}
