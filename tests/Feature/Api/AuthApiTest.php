<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('Auth API', function () {
    describe('POST /api/v1/login', function () {
        it('logs in with valid credentials', function () {
            $user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);

            $response = $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'access_token',
                    'token_type',
                    'expires_in',
                    'user',
                ])
                ->assertJson([
                    'success' => true,
                    'token_type' => 'Bearer',
                ]);
        });

        it('rejects invalid credentials', function () {
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);

            $response = $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            $response->assertStatus(401)
                ->assertJson(['success' => false]);
        });

        it('rejects non-existent email', function () {
            $response = $this->postJson('/api/v1/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'password123',
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'error_type' => 'EMAIL_NOT_FOUND',
                ]);
        });

        it('rejects inactive account', function () {
            User::factory()->create([
                'email' => 'inactive@example.com',
                'password' => Hash::make('password123'),
                'status' => 'inactive',
            ]);

            $response = $this->postJson('/api/v1/login', [
                'email' => 'inactive@example.com',
                'password' => 'password123',
            ]);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'error_type' => 'ACCOUNT_INACTIVE',
                ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/login', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
        });

        it('validates email format', function () {
            $response = $this->postJson('/api/v1/login', [
                'email' => 'not-an-email',
                'password' => 'password123',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });
    });

    describe('POST /api/v1/logout', function () {
        it('logs out authenticated user', function () {
            $user = User::factory()->create(['status' => 'active']);
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->withToken($token)->postJson('/api/v1/logout');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully',
                ]);
        });

        it('returns 401 for unauthenticated user', function () {
            $response = $this->postJson('/api/v1/logout');

            $response->assertStatus(401);
        });
    });

    describe('POST /api/v1/refresh-token', function () {
        it('refreshes token for authenticated user', function () {
            $user = User::factory()->create(['status' => 'active']);
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->withToken($token)->postJson('/api/v1/refresh-token');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'access_token',
                    'token_type',
                    'expires_in',
                ]);
        });

        it('returns 401 for unauthenticated user', function () {
            $response = $this->postJson('/api/v1/refresh-token');

            $response->assertStatus(401);
        });
    });

    describe('POST /api/v1/forgot-password', function () {
        it('validates required email field', function () {
            $response = $this->postJson('/api/v1/forgot-password', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });

        it('validates email format', function () {
            $response = $this->postJson('/api/v1/forgot-password', [
                'email' => 'not-an-email',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });
    });

    describe('POST /api/v1/reset-password', function () {
        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/reset-password', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['token', 'email', 'password']);
        });

        it('validates password confirmation', function () {
            $response = $this->postJson('/api/v1/reset-password', [
                'token' => 'fake-token',
                'email' => 'test@example.com',
                'password' => 'newpassword123',
                'password_confirmation' => 'differentpassword',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        });
    });
});
