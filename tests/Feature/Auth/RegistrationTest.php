<?php

namespace Tests\Feature\Auth;

use App\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled_even_with_a_legacy_invitation(): void
    {
        $invitation = TeamInvitation::factory()->create();

        $this->get('/register?invitation='.$invitation->code)->assertNotFound();
        $this->post('/register', [
            'name' => 'Legacy invitee',
            'email' => $invitation->email,
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
            'invitation' => $invitation->code,
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $invitation->email]);
        $this->assertNull($invitation->fresh()->accepted_at);
    }
}
