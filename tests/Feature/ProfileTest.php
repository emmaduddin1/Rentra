<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SRS FR-AUTH-005 and NFR-SEC-005.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    #[Test]
    public function profile_information_can_be_updated(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone' => '+923005550000',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.profile_updated']);
    }

    #[Test]
    public function a_profile_image_is_validated_and_stored(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->profile_image);
        Storage::disk('public')->assertExists($user->profile_image);
    }

    #[Test]
    public function an_executable_upload_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('profile_image');
    }

    #[Test]
    public function the_last_contact_identifier_cannot_be_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => '', 'phone' => ''])
            ->assertSessionHasErrors(['email', 'phone']);
    }

    #[Test]
    public function the_password_can_be_updated_with_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'a-much-better-password',
                'password_confirmation' => 'a-much-better-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-much-better-password', $user->refresh()->password));

        // NFR-SEC-008 - the audit entry records the event, never the secret.
        $log = AuditLog::where('action', 'user.password_changed')->sole();
        $this->assertStringNotContainsString('a-much-better-password', json_encode($log->toArray()));
    }

    #[Test]
    public function the_password_is_not_updated_with_a_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'not-the-password',
                'password' => 'a-much-better-password',
                'password_confirmation' => 'a-much-better-password',
            ])
            ->assertSessionHasErrors('current_password', errorBag: 'updatePassword');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
