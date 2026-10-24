<?php

namespace Tests\Feature\Api;

use App\Repositories\ApiTokenRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use Tests\Concerns\InteractsWithApi;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use InteractsWithApi;
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSheets();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();
        parent::tearDown();
    }

    public function test_public_endpoints_are_available_without_a_token(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('version', 'v1');

        $this->getJson('/api/v1/study-programs')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'active']]]);

        $this->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
                'links',
            ]);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['program_name', 'academic_year']]);
    }

    public function test_user_can_login_with_email_and_receive_a_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'ayu@psikologi.test',
            'password' => 'password',
            'prodi_id' => $this->apiProdiId(),
            'device_name' => 'Tes Android',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ayu@psikologi.test')
            ->assertJsonPath('user.role', 'mahasiswa')
            ->assertJsonPath('user.role_label', 'Mahasiswa')
            ->assertJsonStructure([
                'access_token',
                'expires_at',
                'token' => ['id', 'name', 'expires_at'],
                'user' => ['id', 'name', 'npm', 'prodi_name'],
            ])
            ->assertJsonMissingPath('user.password_hash');

        $token = (string) $response->json('access_token');
        $record = app(ApiTokenRepository::class)->all()->first();

        $this->assertNotNull($record);
        $this->assertNotSame($token, $record['token_hash']);
        $this->assertSame(hash('sha256', $token), $record['token_hash']);

        $this->withApiToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ayu@psikologi.test');
    }

    public function test_login_accepts_npm_and_rejects_invalid_credentials_or_prodi(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'login' => '2024001',
            'password' => 'password',
            'prodi_id' => $this->apiProdiId(),
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'ayu@psikologi.test',
            'password' => 'salah',
            'prodi_id' => $this->apiProdiId(),
        ])->assertUnprocessable()->assertJsonValidationErrors('login');

        $this->postJson('/api/v1/auth/login', [
            'login' => 'ayu@psikologi.test',
            'password' => 'password',
            'prodi_id' => '01INVALIDPRODI000000000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('prodi_id');
    }

    public function test_inactive_user_and_revoked_token_are_rejected(): void
    {
        $users = app(UserRepository::class);
        $student = $users->findActiveByLogin('ayu@psikologi.test');
        $this->assertNotNull($student);

        $token = $this->apiLogin('ayu@psikologi.test');
        $users->update((string) $student['id'], ['active' => '0']);

        $this->withApiToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $users->update((string) $student['id'], ['active' => '1']);

        $this->withApiToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->withApiToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->apiLogin('ayu@psikologi.test');
        $records = app(ApiTokenRepository::class);
        $record = $records->all()->first();
        $this->assertNotNull($record);

        $records->update((string) $record['id'], [
            'expires_at' => now()->subMinute()->toIso8601String(),
        ]);

        $this->withApiToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_list_and_revoke_other_tokens(): void
    {
        $first = $this->apiLogin('ayu@psikologi.test');
        $second = $this->apiLogin('ayu@psikologi.test');

        $response = $this->withApiToken($first)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $secondId = collect($response->json('data'))
            ->firstWhere('current', false)['id'] ?? null;

        $this->assertNotNull($secondId);

        $this->withApiToken($first)
            ->deleteJson('/api/v1/auth/tokens/'.$secondId)
            ->assertNoContent();

        $this->withApiToken($second)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_profile_and_password_updates_are_safe(): void
    {
        $first = $this->apiLogin('ayu@psikologi.test');
        $second = $this->apiLogin('ayu@psikologi.test');

        $this->withApiToken($first)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Ayu Lestari Baru',
                'phone' => '08123456789',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ayu Lestari Baru')
            ->assertJsonPath('data.phone', '08123456789');

        $this->withApiToken($first)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'salah',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withApiToken($first)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertOk();

        $this->withApiToken($first)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->withApiToken($second)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_role_middleware_prevents_cross_role_access(): void
    {
        $student = $this->apiLogin('ayu@psikologi.test');
        $admin = $this->apiLogin('admin@psikologi.test');

        $this->withApiToken($student)
            ->getJson('/api/v1/staff/dashboard')
            ->assertForbidden();

        $this->withApiToken($admin)
            ->getJson('/api/v1/student/dashboard')
            ->assertForbidden();
    }

    public function test_token_requires_the_correct_prodi(): void
    {
        $programs = app(StudyProgramRepository::class);
        $other = $programs->create([
            'code' => 'bimbingan-konseling',
            'name' => 'Bimbingan dan Konseling',
            'degree' => 'S1',
            'active' => '1',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'ayu@psikologi.test',
            'password' => 'password',
            'prodi_id' => (string) $other['id'],
        ])->assertUnprocessable()->assertJsonValidationErrors('login');
    }
}
