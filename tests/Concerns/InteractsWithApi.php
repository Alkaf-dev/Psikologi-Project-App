<?php

namespace Tests\Concerns;

use App\Repositories\StudyProgramRepository;

trait InteractsWithApi
{
    protected function apiProdiId(): string
    {
        $program = app(StudyProgramRepository::class)->findByCode('psikologi');

        $this->assertNotNull($program);

        return (string) $program['id'];
    }

    protected function apiLogin(string $email = 'admin@psikologi.test', string $password = 'password'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $email,
            'password' => $password,
            'prodi_id' => $this->apiProdiId(),
            'device_name' => 'PHPUnit',
        ])->assertOk();

        $token = $response->json('access_token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }

    protected function withApiToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
