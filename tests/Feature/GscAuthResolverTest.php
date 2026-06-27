<?php

namespace Tests\Feature;

use App\Models\GscConnection;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GscAuthResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_connection_is_stamped_with_current_tenant(): void
    {
        $connection = $this->makeConnection(GscConnection::PROVIDER_OAUTH, GscConnection::KIND_OAUTH_REFRESH, '1//rt');

        $this->assertSame(TenantContext::id(), (int) $connection->tenant_id);
        $this->assertGreaterThan(0, (int) $connection->tenant_id);
    }

    public function test_service_account_connection_yields_token_via_signed_jwt(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.sa', 'expires_in' => 3599])]);

        $saJson = (string) json_encode([
            'type' => 'service_account',
            'client_email' => 'svc@proj.iam.gserviceaccount.com',
            'private_key' => $this->generateRsaPrivateKey(),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
        $connection = $this->makeConnection(GscConnection::PROVIDER_SERVICE_ACCOUNT, GscConnection::KIND_SERVICE_ACCOUNT, $saJson);

        $this->assertSame('ya29.sa', app(GscAuthResolver::class)->accessTokenFor($connection));
        Http::assertSent(fn ($r): bool => $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && is_string($r['assertion']) && substr_count((string) $r['assertion'], '.') === 2);
    }

    public function test_oauth_connection_yields_token_via_refresh(): void
    {
        config([
            'geoflow.google_search_console.oauth_client_id' => 'cid.apps.googleusercontent.com',
            'geoflow.google_search_console.oauth_client_secret' => 'csecret',
        ]);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.oauth', 'expires_in' => 3599])]);

        $connection = $this->makeConnection(GscConnection::PROVIDER_OAUTH, GscConnection::KIND_OAUTH_REFRESH, '1//refresh-abc');

        $this->assertSame('ya29.oauth', app(GscAuthResolver::class)->accessTokenFor($connection));
        Http::assertSent(fn ($r): bool => $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === '1//refresh-abc' && $r['client_id'] === 'cid.apps.googleusercontent.com');
    }

    private function makeConnection(string $provider, string $kind, string $plain): GscConnection
    {
        return GscConnection::query()->create([
            'name' => 'conn',
            'provider' => $provider,
            'email' => 'a@b.com',
            'secret_kind' => $kind,
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt($plain),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
        ]);
    }

    private function generateRsaPrivateKey(): string
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($resource, 'openssl 无法生成测试 RSA 私钥');
        openssl_pkey_export($resource, $privateKeyPem);

        return (string) $privateKeyPem;
    }
}
