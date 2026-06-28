<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchGscJob;
use App\Models\GscConnection;
use App\Models\GscProperty;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use App\Services\GeoFlow\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Services\GeoFlow\GoogleSearchConsole\GscInsightsService;
use App\Services\GeoFlow\GoogleSearchConsole\GscOrchestrator;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\GscOauthAppConfig;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * 谷歌搜录后台：以「连接」为中心。
 * 平台超管一次性配置 OAuth 应用（DB），租户用户一键连接 Google → 勾选已验证站点。
 */
class GoogleSearchConsoleController extends Controller
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly GscOauthAppConfig $oauthApp,
        private readonly GoogleSearchConsoleClient $client,
        private readonly GscOrchestrator $orchestrator,
        private readonly GscInsightsService $insights,
    ) {}

    public function index(): View
    {
        // 超管跨租户只读总览：TenantScope 对 super_admin 自动放行，查询即返回全部租户的连接/站点。
        $connections = GscConnection::query()->with(['tenant', 'properties.latestSnapshot'])->orderByDesc('id')->get();
        $properties = GscProperty::query()->with(['tenant', 'latestSnapshot'])->orderByDesc('id')->get();

        return view('admin.google-search-console.index', [
            'pageTitle' => __('admin.gsc.page_title'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
            'connections' => $connections,
            'properties' => $properties,
            'oauthConfigured' => $this->oauthApp->isConfigured(),
            'isSuperAdmin' => $this->isSuperAdmin(),
            'stats' => [
                'connections' => $connections->count(),
                'properties' => $properties->count(),
                'active' => $properties->where('status', 'active')->count(),
            ],
        ]);
    }

    public function settings(): View|RedirectResponse
    {
        if (! $this->isSuperAdmin()) {
            abort(403);
        }

        return view('admin.google-search-console.settings', [
            'pageTitle' => __('admin.gsc.settings_heading'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
            'clientId' => $this->oauthApp->clientId(),
            'hasSecret' => $this->oauthApp->clientSecret() !== '',
            'redirectUri' => $this->oauthApp->redirectUri(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        if (! $this->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:300'],
            'client_secret' => ['nullable', 'string', 'max:300'],
            'redirect_uri' => ['nullable', 'string', 'max:300'],
        ]);

        $this->oauthApp->update(
            (string) $data['client_id'],
            (string) ($data['client_secret'] ?? ''),
            (string) ($data['redirect_uri'] ?? $this->oauthApp->redirectUri()),
        );

        return redirect()->route('admin.google-search-console.settings')
            ->with('message', __('admin.gsc.message.settings_saved'));
    }

    /**
     * 一键连接：跳转 Google 同意页（offline 以取 refresh token）。
     */
    public function connect(): RedirectResponse
    {
        if ($block = $this->denySuperAdminManage()) {
            return $block;
        }
        if (! $this->oauthApp->isConfigured()) {
            return redirect()->route('admin.google-search-console.index')
                ->withErrors(__('admin.gsc.message.oauth_not_configured'));
        }

        $state = Str::random(40);
        session(['gsc_oauth_state' => $state]);

        $query = http_build_query([
            'client_id' => $this->oauthApp->clientId(),
            'redirect_uri' => $this->oauthApp->redirectUri(),
            'response_type' => 'code',
            'scope' => GscAuthResolver::SCOPE.' openid email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function oauthCallback(Request $request): RedirectResponse
    {
        $expectedState = (string) session('gsc_oauth_state');
        session()->forget('gsc_oauth_state');

        if ($request->filled('error') || ! $request->filled('code') || (string) $request->input('state') !== $expectedState || $expectedState === '') {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.oauth_denied'));
        }

        $tokenUri = 'https://oauth2.googleapis.com/token';
        $response = Http::asForm()
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($tokenUri))
            ->post($tokenUri, [
                'code' => (string) $request->input('code'),
                'client_id' => $this->oauthApp->clientId(),
                'client_secret' => $this->oauthApp->clientSecret(),
                'redirect_uri' => $this->oauthApp->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        $refreshToken = (string) $response->json('refresh_token', '');
        if (! $response->successful() || $refreshToken === '') {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.oauth_no_refresh'));
        }

        $email = $this->emailFromIdToken((string) $response->json('id_token', ''));
        $connection = GscConnection::query()->updateOrCreate(
            ['provider' => GscConnection::PROVIDER_OAUTH, 'email' => $email],
            [
                'name' => $email ?: __('admin.gsc.connection.oauth_default_name'),
                'secret_kind' => GscConnection::KIND_OAUTH_REFRESH,
                'secret_ciphertext' => $this->apiKeyCrypto->encrypt($refreshToken),
                'status' => 'active',
                'scopes' => [GscAuthResolver::SCOPE],
                'created_by_admin_id' => auth('admin')->id(),
            ],
        );

        return redirect()->route('admin.google-search-console.sites', $connection->id)
            ->with('message', __('admin.gsc.message.oauth_connected'));
    }

    public function createServiceAccount(): View|RedirectResponse
    {
        if ($block = $this->denySuperAdminManage()) {
            return $block;
        }

        return view('admin.google-search-console.service-account', [
            'pageTitle' => __('admin.gsc.sa_heading'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
        ]);
    }

    public function storeServiceAccount(Request $request): RedirectResponse
    {
        if ($block = $this->denySuperAdminManage()) {
            return $block;
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'service_account_json' => ['required', 'string', 'max:8000'],
        ]);

        $sa = json_decode((string) $data['service_account_json'], true);
        if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            return back()->withErrors(__('admin.gsc.message.sa_invalid'))->withInput();
        }

        $connection = GscConnection::query()->create([
            'name' => $data['name'] ?: (string) $sa['client_email'],
            'provider' => GscConnection::PROVIDER_SERVICE_ACCOUNT,
            'email' => (string) $sa['client_email'],
            'secret_kind' => GscConnection::KIND_SERVICE_ACCOUNT,
            'secret_ciphertext' => $this->apiKeyCrypto->encrypt((string) $data['service_account_json']),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.google-search-console.sites', $connection->id)
            ->with('message', __('admin.gsc.message.sa_connected'));
    }

    /**
     * 列出连接名下已验证站点，供勾选加入监控。
     */
    public function sites(int $connectionId): View|RedirectResponse
    {
        if ($block = $this->denySuperAdminManage()) {
            return $block;
        }

        $connection = GscConnection::query()->whereKey($connectionId)->first();
        if ($connection === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.connection_not_found'));
        }

        $verified = [];
        $error = null;
        try {
            $verified = $this->client->listSites($connection);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $existing = GscProperty::query()->where('gsc_connection_id', $connection->id)->pluck('site_url')->all();

        return view('admin.google-search-console.sites', [
            'pageTitle' => __('admin.gsc.sites_heading'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
            'connection' => $connection,
            'verified' => $verified,
            'existing' => $existing,
            'listError' => $error,
        ]);
    }

    public function addSites(Request $request, int $connectionId): RedirectResponse
    {
        if ($block = $this->denySuperAdminManage()) {
            return $block;
        }

        $connection = GscConnection::query()->whereKey($connectionId)->first();
        if ($connection === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.connection_not_found'));
        }

        $data = $request->validate([
            'sites' => ['required', 'array', 'min:1'],
            'sites.*' => ['string', 'max:300'],
        ]);

        $added = 0;
        foreach (array_unique($data['sites']) as $siteUrl) {
            $siteUrl = trim((string) $siteUrl);
            if ($siteUrl === '') {
                continue;
            }
            $property = GscProperty::query()->firstOrCreate(
                ['gsc_connection_id' => $connection->id, 'site_url' => $siteUrl],
                [
                    'name' => $siteUrl,
                    'schedule' => 'daily',
                    'status' => 'active',
                    'created_by_admin_id' => auth('admin')->id(),
                ],
            );
            if ($property->wasRecentlyCreated) {
                $added++;
            }
        }

        return redirect()->route('admin.google-search-console.index')
            ->with('message', __('admin.gsc.message.sites_added', ['count' => $added]));
    }

    public function show(int $propertyId): View|RedirectResponse
    {
        $property = GscProperty::query()->with('connection')->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        $latestSearch = $property->snapshots()->where('type', GscSnapshot::TYPE_SEARCH_ANALYTICS)->latest('id')->first();
        $latestSitemap = $property->snapshots()->where('type', GscSnapshot::TYPE_SITEMAPS)->latest('id')->first();
        $latestInspection = $property->snapshots()->where('type', GscSnapshot::TYPE_URL_INSPECTION)->latest('id')->first();

        $metrics = $latestSearch
            ? GscSearchMetric::query()->where('gsc_snapshot_id', $latestSearch->id)->orderByDesc('clicks')->orderByDesc('impressions')->limit(100)->get()
            : collect();
        $inspections = $latestInspection
            ? GscUrlInspection::query()->where('gsc_snapshot_id', $latestInspection->id)->orderByDesc('id')->get()
            : collect();

        return view('admin.google-search-console.show', [
            'pageTitle' => __('admin.gsc.page_title'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
            'property' => $property,
            'latestSearch' => $latestSearch,
            'latestSitemap' => $latestSitemap,
            'latestInspection' => $latestInspection,
            'metrics' => $metrics,
            'inspections' => $inspections,
            'insights' => $this->insights->build($property),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }

    public function fetch(int $propertyId): RedirectResponse
    {
        $property = GscProperty::query()->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        FetchGscJob::dispatch((int) $property->id)->onQueue('trends');

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('message', __('admin.gsc.message.fetch_queued'));
    }

    public function inspect(Request $request, int $propertyId): RedirectResponse
    {
        $property = GscProperty::query()->with('connection')->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        $payload = $request->validate(['urls' => ['required', 'string', 'max:8000']]);
        $urls = array_slice(array_values(array_filter(array_map(
            'trim',
            preg_split('/\R/u', (string) $payload['urls']) ?: []
        ), static fn (string $u): bool => $u !== '')), 0, 20);

        if ($urls === []) {
            return redirect()->route('admin.google-search-console.show', $property->id)
                ->withErrors(__('admin.gsc.message.inspect_empty'));
        }

        $this->orchestrator->inspectUrls($property, $urls);

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('message', __('admin.gsc.message.inspect_done', ['count' => count($urls)]));
    }

    public function destroyProperty(int $propertyId): RedirectResponse
    {
        $property = GscProperty::query()->whereKey($propertyId)->first();
        if ($property !== null) {
            $property->delete();
        }

        return redirect()->route('admin.google-search-console.index')
            ->with('message', __('admin.gsc.message.property_removed'));
    }

    public function disconnect(int $connectionId): RedirectResponse
    {
        $connection = GscConnection::query()->whereKey($connectionId)->first();
        if ($connection !== null) {
            $connection->delete();
        }

        return redirect()->route('admin.google-search-console.index')
            ->with('message', __('admin.gsc.message.disconnected'));
    }

    private function isSuperAdmin(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null && method_exists($admin, 'isSuperAdmin') && $admin->isSuperAdmin();
    }

    /**
     * 超管为跨租户只读总览：自助创建/接入类动作只属于具体租户，超管一律拦回。
     */
    private function denySuperAdminManage(): ?RedirectResponse
    {
        if ($this->isSuperAdmin()) {
            return redirect()->route('admin.google-search-console.index')
                ->withErrors(__('admin.gsc.message.super_readonly'));
        }

        return null;
    }

    private function emailFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        return is_array($payload) && ! empty($payload['email']) ? (string) $payload['email'] : null;
    }
}
