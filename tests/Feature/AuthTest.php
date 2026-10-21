<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_login(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
    }

    public function test_spa_logs_in_with_the_session_and_can_call_the_api(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret-password']);

        $this->postJson('/login', ['email' => 'ana@example.com', 'password' => 'wrong'])->assertJsonValidationErrors('email');

        $this->postJson('/login', ['email' => 'ana@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        // the browser sends its origin, which marks the API request as stateful (session cookie auth)
        $this->withHeader('Referer', 'http://localhost')->getJson('/api/documents')->assertOk();
    }

    public function test_spa_routes_serve_the_app_shell(): void
    {
        $this->withoutVite();

        $this->get('/edit/data-management')->assertOk()->assertSee('<div id="app"', false);
    }
}
