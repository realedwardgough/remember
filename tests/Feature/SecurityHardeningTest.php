<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateRegistrationInvite;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function turnstile_is_off_by_default_and_makes_no_external_requests(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->assertFalse((bool) config('security.turnstile.enabled'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    #[Test]
    public function enabled_turnstile_requires_a_valid_token_hostname_and_action(): void
    {
        config(['security.turnstile.enabled' => true, 'security.turnstile.secret_key' => 'secret', 'security.turnstile.hostname' => 'remember.test']);
        $user = User::factory()->create();
        Http::fake(fn ($request) => Http::response(['success' => true, 'hostname' => $request['response'] === 'valid-token' ? 'remember.test' : 'evil.example', 'action' => 'login']));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('cf-turnstile-response');
        Http::assertNothingSent();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'token'])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'valid-token'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'valid-token'])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    #[Test]
    public function turnstile_fails_closed_for_errors_wrong_actions_and_missing_configuration(): void
    {
        config(['security.turnstile.enabled' => true, 'security.turnstile.secret_key' => 'secret', 'security.turnstile.hostname' => 'remember.test']);
        $user = User::factory()->create();
        $payload = ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'token'];
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => true, 'hostname' => 'remember.test', 'action' => 'register'])->push([], 503)]);
        $this->post('/login', $payload)->assertSessionHasErrors('cf-turnstile-response');
        $payload['cf-turnstile-response'] = 'outage-token';
        $this->post('/login', $payload)->assertSessionHasErrors('cf-turnstile-response');
        config(['security.turnstile.secret_key' => '']);
        $this->post('/login', $payload)->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    #[Test]
    public function public_form_throttling_happens_before_external_challenge_verification(): void
    {
        config(['security.turnstile.enabled' => true, 'security.turnstile.secret_key' => 'secret', 'security.turnstile.hostname' => 'remember.test']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->post('/register', ['cf-turnstile-response' => 'token-'.$attempt])->assertSessionHasErrors('cf-turnstile-response');
        }
        $this->post('/register', ['cf-turnstile-response' => 'another-token'])->assertStatus(429);
        Http::assertSentCount(30);
    }

    #[Test]
    public function invitations_expire_and_can_be_bound_to_an_email_address(): void
    {
        $result = app(CreateRegistrationInvite::class)->handle('family-member', 'family@example.com');
        $this->assertNotNull($result->invite->expires_at);
        $this->post('/register', ['name' => 'Family', 'email' => 'other@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'invitation_token' => $result->token])->assertSessionHasErrors('email');
        $this->travel(8)->days();
        $this->get($result->url)->assertNotFound();
        $this->post('/register', ['name' => 'Family', 'email' => 'family@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'invitation_token' => $result->token])->assertSessionHasErrors('invitation_token');
        $this->assertGuest();
    }

    #[Test]
    public function email_changes_require_the_current_password_and_confirmation_of_the_new_address(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $payload = ['name' => $user->name, 'email' => 'new@example.com'];
        $this->actingAs($user)->put('/user/profile-information', $payload)->assertSessionHasErrors('current_password', null, 'updateProfileInformation');
        $this->put('/user/profile-information', [...$payload, 'current_password' => 'password'])->assertRedirect();
        $this->assertSame('old@example.com', $user->fresh()->email);
        $url = null;
        Notification::assertSentOnDemand(VerifyEmailChange::class, function ($notification, $channels, $notifiable) use (&$url): bool {
            $url = $notification->url;
            return $notifiable->routes['mail'] === 'new@example.com';
        });
        $this->get($url)->assertRedirect(route('profile.edit'));
        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get($url)->assertForbidden();
    }

    #[Test]
    public function email_confirmation_links_reject_tampering_expiry_and_other_accounts(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('profile.email.confirm', now()->addMinutes(30), [
            'user' => (string) $user->id, 'email' => 'new@example.com', 'previous' => hash('sha256', $user->email.'|'.$user->password),
        ]);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($user)->get(str_replace('new%40example.com', 'attacker%40example.com', $url))->assertForbidden();
        $this->travel(31)->minutes();
        $this->get($url)->assertForbidden();
    }

    #[Test]
    public function password_changes_revoke_other_database_sessions_and_keep_the_current_session(): void
    {
        config(['session.driver' => 'database', 'session.encrypt' => false]);
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'stolen-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->actingAs($user)->put('/user/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
        $this->assertAuthenticatedAs($user);
        $this->get('/profile')->assertOk();
    }

    #[Test]
    public function sessions_with_an_old_password_hash_are_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['password_hash_web' => 'outdated-password-hash'])->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function two_factor_setup_requires_password_confirmation_and_hides_secrets_from_shared_user_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile/security')->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()])->post('/profile/security/enable')->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->fresh()->toArray());
        $this->get('/profile/security')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/AccountSecurity')->where('pending', true)->has('recoveryCodes', 8));
        $this->post('/profile/security/confirm', ['code' => 'wrong'])->assertSessionHasErrors('code');
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $this->post('/profile/security/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect();
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    #[Test]
    public function an_enrolled_account_cannot_log_in_without_a_second_factor_and_recovery_codes_are_single_use(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $recovery = $user->recoveryCodes()[0];
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes());
    }

    #[Test]
    public function setup_requires_a_configured_token_when_protected(): void
    {
        config(['security.setup_token' => 'private-setup-token']);
        $this->get('/setup')->assertForbidden();
        $this->get('/setup?setup_token=wrong')->assertForbidden();
        $this->get('/setup?setup_token=private-setup-token')->assertOk();
        $this->get('/setup')->assertOk();
    }

    #[Test]
    public function response_headers_prevent_framing_and_mime_sniffing(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->app->detectEnvironment(fn () => 'production');
        $this->get('https://localhost/login')->assertHeader('Content-Security-Policy')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    #[Test]
    public function oversized_decoded_timeline_photos_are_rejected_before_processing(): void
    {
        config(['filesystems.media_disk' => 'local']);
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', [
            'title' => 'Photo', 'post_type' => \App\Enum\TimelinePostType::cases()[0]->value, 'published_at' => '2026-11-10',
            'media' => [UploadedFile::fake()->image('too-wide.png', 8193, 1)],
        ])->assertSessionHasErrors('media');
        $this->assertDatabaseCount('posts', 0);
    }
}
