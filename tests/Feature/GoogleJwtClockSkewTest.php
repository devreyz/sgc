<?php

namespace Tests\Feature;

use App\Auth\SecureGoogleProvider;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class GoogleJwtClockSkewTest extends TestCase
{
    public function test_valid_google_token_one_second_ahead_is_accepted_and_global_leeway_is_restored(): void
    {
        $key = RSA::createKey(2048);
        $privateKey = $key->toString('PKCS8');
        $publicKey = $key->getPublicKey();
        $exportedJwk = json_decode($publicKey->toString('JWK'), true, flags: JSON_THROW_ON_ERROR);
        $publicJwk = $exportedJwk['keys'][0] ?? $exportedJwk;

        $timestamp = time();
        $clientId = 'google-client-id.apps.googleusercontent.com';
        $token = JWT::encode([
            'iss' => 'https://accounts.google.com',
            'aud' => $clientId,
            'sub' => 'google-subject',
            'iat' => $timestamp + 1,
            'exp' => $timestamp + 300,
        ], $privateKey, 'RS256', 'test-key');

        $jwks = ['keys' => [[...$publicJwk,
            'kid' => 'test-key',
            'use' => 'sig',
            'alg' => 'RS256',
        ]]];

        $provider = new class(Request::create('/auth/google/callback'), $clientId, 'secret', '/callback') extends SecureGoogleProvider
        {
            public function decodeForTest(string $token): array
            {
                return $this->getUserFromJwtToken($token);
            }
        };
        $provider->setHttpClient(new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks)),
            ])),
        ]));

        config()->set('security.google_jwt_clock_skew_seconds', 10);
        $previousLeeway = JWT::$leeway;
        $previousTimestamp = JWT::$timestamp;
        JWT::$leeway = 3;
        JWT::$timestamp = $timestamp;

        try {
            $claims = $provider->decodeForTest($token);
            $this->assertSame('google-subject', $claims['sub']);
            $this->assertSame(3, JWT::$leeway);

            $provider->setHttpClient(new Client([
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(200, ['Content-Type' => 'application/json'], json_encode($jwks)),
                ])),
            ]));
            config()->set('security.google_jwt_clock_skew_seconds', 999);
            $farFutureToken = JWT::encode([
                'iss' => 'https://accounts.google.com',
                'aud' => $clientId,
                'sub' => 'future-subject',
                'iat' => $timestamp + 61,
                'exp' => $timestamp + 300,
            ], $privateKey, 'RS256', 'test-key');

            try {
                $provider->decodeForTest($farFutureToken);
                $this->fail('The JWT clock skew must remain capped at 60 seconds.');
            } catch (\Exception $exception) {
                $this->assertStringContainsString('iat prior', $exception->getMessage());
                $this->assertSame(3, JWT::$leeway);
            }
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }
    }
}
