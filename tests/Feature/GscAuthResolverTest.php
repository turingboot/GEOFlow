<?php

namespace Tests\Feature;

use App\Models\GscProperty;
use App\Models\GscPropertySecret;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GscAuthResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_property_is_stamped_with_current_tenant(): void
    {
        $property = GscProperty::query()->create([
            'name' => '示例站点',
            'site_url' => 'sc-domain:example.com',
            'auth_type' => 'service_account',
            'status' => 'active',
        ]);

        $this->assertSame(TenantContext::id(), (int) $property->tenant_id);
        $this->assertGreaterThan(0, (int) $property->tenant_id);
    }

    public function test_service_account_secret_yields_access_token_via_signed_jwt(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.sa-token', 'expires_in' => 3599], 200),
        ]);

        $privateKey = $this->generateRsaPrivateKey();
        $saJson = (string) json_encode([
            'type' => 'service_account',
            'client_email' => 'svc@proj.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $property = $this->makePropertyWithSecret('service_account', GscPropertySecret::KIND_SERVICE_ACCOUNT, $saJson);

        $token = app(GscAuthResolver::class)->accessTokenFor($property);

        $this->assertSame('ya29.sa-token', $token);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && is_string($request['assertion'])
                && substr_count((string) $request['assertion'], '.') === 2;
        });
    }

    public function test_oauth_refresh_token_secret_yields_access_token(): void
    {
        config([
            'geoflow.google_search_console.oauth_client_id' => 'client-123.apps.googleusercontent.com',
            'geoflow.google_search_console.oauth_client_secret' => 'secret-xyz',
        ]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.oauth-token', 'expires_in' => 3599], 200),
        ]);

        $property = $this->makePropertyWithSecret('oauth', GscPropertySecret::KIND_OAUTH_REFRESH, '1//refresh-token-abc');

        $token = app(GscAuthResolver::class)->accessTokenFor($property);

        $this->assertSame('ya29.oauth-token', $token);
        Http::assertSent(function ($request): bool {
            return $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === '1//refresh-token-abc'
                && $request['client_id'] === 'client-123.apps.googleusercontent.com';
        });
    }

    private function makePropertyWithSecret(string $authType, string $kind, string $plainSecret): GscProperty
    {
        $property = GscProperty::query()->create([
            'name' => '站点 '.$kind,
            'site_url' => 'sc-domain:example.com',
            'auth_type' => $authType,
            'status' => 'active',
        ]);

        $property->secrets()->create([
            'key_id' => 'gsc_'.bin2hex(random_bytes(6)),
            'secret_kind' => $kind,
            'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt($plainSecret),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
        ]);

        return $property->fresh(['activeSecret']);
    }

    private function generateRsaPrivateKey(): string
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource, 'openssl 无法生成测试 RSA 私钥');

        openssl_pkey_export($resource, $privateKeyPem);

        return (string) $privateKeyPem;
    }
}
