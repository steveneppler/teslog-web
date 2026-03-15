<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_first_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_validates_timezone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'timezone' => 'Invalid/Timezone',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('timezone');
    }

    public function test_register_validates_distance_unit(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'distance_unit' => 'furlongs',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('distance_unit');
    }

    public function test_register_validates_temperature_unit(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'temperature_unit' => 'K',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('temperature_unit');
    }

    public function test_register_with_valid_optional_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'timezone' => 'America/Los_Angeles',
            'distance_unit' => 'km',
            'temperature_unit' => 'C',
            'currency' => 'EUR',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'timezone' => 'America/Los_Angeles',
            'distance_unit' => 'km',
        ]);
    }

    public function test_login_returns_token(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_token_scopes_are_not_user_controlled(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password',
            'scopes' => ['admin', 'super-admin'],
        ]);

        $response->assertOk();

        $user = User::where('email', 'user@example.com')->first();
        $token = $user->tokens()->latest()->first();
        $this->assertEquals(['read', 'write', 'commands'], $token->abilities);
    }

    public function test_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['read']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_user_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/user')->assertStatus(401);
    }

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }
}
