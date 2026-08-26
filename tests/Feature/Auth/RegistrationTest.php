<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-AUTH-001 - registration with a unique email or phone number.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create an account');
    }

    #[Test]
    public function a_renter_can_register_with_an_email(): void
    {
        $response = $this->post(route('register'), [
            'role' => UserRole::Renter->value,
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('renter.dashboard'));

        $user = User::where('email', 'ayesha@example.com')->sole();
        $this->assertSame(UserRole::Renter, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    #[Test]
    public function an_owner_can_register_with_a_phone_number_only(): void
    {
        $response = $this->post(route('register'), [
            'role' => UserRole::Owner->value,
            'name' => 'Bilal Ahmed',
            'phone' => '+923001234567',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('owner.dashboard'));

        $user = User::where('phone', '+923001234567')->sole();
        $this->assertNull($user->email);
        $this->assertTrue($user->isOwner());
    }

    #[Test]
    public function registration_requires_an_email_or_a_phone_number(): void
    {
        $this->post(route('register'), [
            'role' => UserRole::Renter->value,
            'name' => 'No Contact',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors(['email', 'phone']);

        $this->assertGuest();
    }

    #[Test]
    public function duplicate_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '+923009999999']);

        $this->post(route('register'), [
            'role' => UserRole::Renter->value,
            'name' => 'Copycat',
            'email' => 'taken@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->post(route('register'), [
            'role' => UserRole::Renter->value,
            'name' => 'Copycat',
            'phone' => '+923009999999',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('phone');
    }

    #[Test]
    public function an_unknown_role_cannot_self_register(): void
    {
        // SRS 19 - only the configured roles are self-registerable.
        $this->post(route('register'), [
            'role' => 'applicant',
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    #[Test]
    public function registration_is_audited_without_storing_the_password(): void
    {
        $this->post(route('register'), [
            'role' => UserRole::Renter->value,
            'name' => 'Audited User',
            'email' => 'audited@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $log = AuditLog::where('action', 'user.registered')->sole();

        $this->assertSame(User::class, $log->subject_type);
        $this->assertStringNotContainsString('correct-horse-battery', json_encode($log->new_values));
    }
}
