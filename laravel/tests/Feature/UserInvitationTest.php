<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNewUserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Holding the welcome email at create time, then sending held invitations
 * individually or in bulk.
 */
class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'welcome_email_sent_at' => now(),
        ]), ['*']);
    }

    private function createUser(array $overrides = []): array
    {
        return $this->postJson('/api/v1/users', array_merge([
            'first_name' => 'Pat',
            'last_name'  => 'Doe',
            'email'      => 'pat' . fake()->unique()->numberBetween(1, 99999) . '@example.com',
            'role'       => 'office_staff',
        ], $overrides))->json();
    }

    public function test_creating_a_user_sends_the_welcome_email_by_default(): void
    {
        $res = $this->postJson('/api/v1/users', [
            'first_name' => 'Sam', 'last_name' => 'Lee',
            'email' => 'sam@example.com', 'role' => 'office_staff',
        ])->assertCreated();

        $res->assertJsonPath('email_sent', true)->assertJsonPath('user.invitation_pending', false);
        $this->assertNotNull(User::where('email', 'sam@example.com')->value('welcome_email_sent_at'));
        Notification::assertSentTo(User::where('email', 'sam@example.com')->first(), WelcomeNewUserNotification::class);
    }

    public function test_creating_a_user_can_hold_the_welcome_email(): void
    {
        $res = $this->postJson('/api/v1/users', [
            'first_name' => 'Held', 'last_name' => 'User',
            'email' => 'held@example.com', 'role' => 'office_staff',
            'send_welcome_email' => false,
        ])->assertCreated();

        $res->assertJsonPath('welcome_held', true)
            ->assertJsonPath('email_sent', false)
            ->assertJsonPath('user.invitation_pending', true);

        $user = User::where('email', 'held@example.com')->first();
        $this->assertNull($user->welcome_email_sent_at);
        Notification::assertNothingSentTo($user);

        // It shows as pending in the list + statistics.
        $row = collect($this->getJson('/api/v1/users')->json())->firstWhere('email', 'held@example.com');
        $this->assertTrue($row['invitation_pending']);
        $this->assertSame(1, $this->getJson('/api/v1/users/statistics')->json('pending_invitations'));
    }

    public function test_sending_one_held_invitation_delivers_it_and_stamps_it(): void
    {
        $this->createUser(['email' => 'one@example.com', 'send_welcome_email' => false]);
        $user = User::where('email', 'one@example.com')->first();

        $this->postJson("/api/v1/users/{$user->id}/resend-invitation")
            ->assertOk()
            ->assertJsonPath('email_sent', true)
            ->assertJsonPath('message', 'Invitation sent with a temporary password.');

        Notification::assertSentTo($user, WelcomeNewUserNotification::class);
        $this->assertNotNull($user->fresh()->welcome_email_sent_at);
    }

    public function test_bulk_send_delivers_every_held_invitation_and_skips_inactive_and_already_sent(): void
    {
        // 3 held (one inactive), 1 already invited earlier.
        $this->createUser(['email' => 'h1@example.com', 'send_welcome_email' => false]);
        $this->createUser(['email' => 'h2@example.com', 'send_welcome_email' => false]);
        $this->createUser(['email' => 'h3@example.com', 'send_welcome_email' => false, 'is_active' => false]);
        $this->createUser(['email' => 'already@example.com']); // sent on create

        Notification::fake(); // reset counts after the "already" send above

        $res = $this->postJson('/api/v1/users/send-pending-invitations')->assertOk();

        $res->assertJsonPath('sent', 2)->assertJsonPath('skipped_inactive', 1);
        $this->assertEmpty($res->json('failed'));

        Notification::assertSentTo(User::where('email', 'h1@example.com')->first(), WelcomeNewUserNotification::class);
        Notification::assertSentTo(User::where('email', 'h2@example.com')->first(), WelcomeNewUserNotification::class);
        Notification::assertNotSentTo(User::where('email', 'h3@example.com')->first(), WelcomeNewUserNotification::class);
        Notification::assertNotSentTo(User::where('email', 'already@example.com')->first(), WelcomeNewUserNotification::class);

        $this->assertNotNull(User::where('email', 'h1@example.com')->value('welcome_email_sent_at'));
        $this->assertNull(User::where('email', 'h3@example.com')->value('welcome_email_sent_at')); // still held

        // Nothing left to send.
        $this->postJson('/api/v1/users/send-pending-invitations')->assertOk()->assertJsonPath('sent', 0);
    }

    public function test_bulk_send_can_target_a_subset(): void
    {
        $this->createUser(['email' => 's1@example.com', 'send_welcome_email' => false]);
        $this->createUser(['email' => 's2@example.com', 'send_welcome_email' => false]);
        $only = User::where('email', 's1@example.com')->first();

        $this->postJson('/api/v1/users/send-pending-invitations', ['user_ids' => [$only->id]])
            ->assertOk()->assertJsonPath('sent', 1);

        $this->assertNotNull($only->fresh()->welcome_email_sent_at);
        $this->assertNull(User::where('email', 's2@example.com')->value('welcome_email_sent_at'));
    }
}
