<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchGscJob;
use App\Models\GscProperty;
use App\Models\GscPropertySecret;
use App\Models\GscSearchMetric;
use App\Models\GscSnapshot;
use App\Models\GscUrlInspection;
use App\Services\GeoFlow\GoogleSearchConsole\GscAuthResolver;
use App\Services\GeoFlow\GoogleSearchConsole\GscOrchestrator;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OutboundHttpProxy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * 谷歌搜录（Google Search Console 监控）后台控制器。
 * 支持服务账号（粘贴 SA JSON）与 OAuth（连接 Google 账号）两种认证。
 */
class GoogleSearchConsoleController extends Controller
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly GscOrchestrator $orchestrator,
    ) {}

    public function index(): View
    {
        $properties = GscProperty::query()
            ->with(['latestSnapshot'])
            ->orderByDesc('id')
            ->get();

        return view('admin.google-search-console.index', [
            'pageTitle' => __('admin.gsc.page_title'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
            'properties' => $properties,
            'stats' => [
                'total' => $properties->count(),
                'active' => $properties->where('status', 'active')->count(),
                'oauth' => $properties->where('auth_type', 'oauth')->count(),
                'service_account' => $properties->where('auth_type', 'service_account')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.google-search-console.create', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProperty($request);

        $property = GscProperty::query()->create([
            'name' => $data['name'],
            'site_url' => trim((string) $data['site_url']),
            'auth_type' => $data['auth_type'],
            'schedule' => $data['schedule'] ?? 'manual',
            'status' => 'active',
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        $this->maybeStoreServiceAccount($property, $data);

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('message', __('admin.gsc.message.created'));
    }

    public function edit(int $propertyId): View|RedirectResponse
    {
        $property = GscProperty::query()->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        return view('admin.google-search-console.edit', $this->formData($property));
    }

    public function update(Request $request, int $propertyId): RedirectResponse
    {
        $property = GscProperty::query()->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        $data = $this->validateProperty($request);
        $property->update([
            'name' => $data['name'],
            'site_url' => trim((string) $data['site_url']),
            'auth_type' => $data['auth_type'],
            'schedule' => $data['schedule'] ?? 'manual',
        ]);

        $this->maybeStoreServiceAccount($property, $data);

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('message', __('admin.gsc.message.updated'));
    }

    public function show(int $propertyId): View|RedirectResponse
    {
        $property = GscProperty::query()->with(['activeSecret'])->whereKey($propertyId)->first();
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
            'revealedSecret' => session('gsc_secret'),
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
        $property = GscProperty::query()->whereKey($propertyId)->first();
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

        // 收录抽样为逐 URL 调用 Google，受配额限制，单次后台请求上限 20 条，直接内联执行。
        $this->orchestrator->inspectUrls($property, $urls);

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('message', __('admin.gsc.message.inspect_done', ['count' => count($urls)]));
    }

    public function revealSecret(Request $request, int $propertyId): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin || ! method_exists($admin, 'isSuperAdmin') || ! $admin->isSuperAdmin()) {
            abort(403);
        }

        $property = GscProperty::query()->with('activeSecret')->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('password'), (string) $admin->password)) {
            return redirect()->route('admin.google-search-console.show', $property->id)
                ->withErrors(['password' => __('admin.gsc.message.bad_password')]);
        }

        $secret = $property->activeSecret;
        $plain = $secret ? $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext) : '';

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('gsc_secret', $plain);
    }

    /**
     * OAuth：跳转到 Google 同意页（access_type=offline 以拿到 refresh token）。
     */
    public function oauthConnect(int $propertyId): RedirectResponse
    {
        $property = GscProperty::query()->whereKey($propertyId)->first();
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        $clientId = (string) config('geoflow.google_search_console.oauth_client_id', '');
        if ($clientId === '') {
            return redirect()->route('admin.google-search-console.show', $property->id)
                ->withErrors(__('admin.gsc.message.oauth_not_configured'));
        }

        session(['gsc_oauth_property_id' => (int) $property->id]);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $this->oauthRedirectUri(),
            'response_type' => 'code',
            'scope' => GscAuthResolver::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    /**
     * OAuth 回调：用授权码换 refresh token 并加密落库。
     */
    public function oauthCallback(Request $request): RedirectResponse
    {
        $propertyId = (int) session('gsc_oauth_property_id');
        session()->forget('gsc_oauth_property_id');
        $property = $propertyId > 0 ? GscProperty::query()->whereKey($propertyId)->first() : null;
        if ($property === null) {
            return redirect()->route('admin.google-search-console.index')->withErrors(__('admin.gsc.message.not_found'));
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('admin.google-search-console.show', $property->id)
                ->withErrors(__('admin.gsc.message.oauth_denied'));
        }

        $tokenUri = 'https://oauth2.googleapis.com/token';
        $response = Http::asForm()
            ->withOptions(OutboundHttpProxy::httpClientOptionsForUrl($tokenUri))
            ->post($tokenUri, [
                'code' => (string) $request->input('code'),
                'client_id' => (string) config('geoflow.google_search_console.oauth_client_id', ''),
                'client_secret' => (string) config('geoflow.google_search_console.oauth_client_secret', ''),
                'redirect_uri' => $this->oauthRedirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        $refreshToken = (string) $response->json('refresh_token', '');
        if (! $response->successful() || $refreshToken === '') {
            return redirect()->route('admin.google-search-console.show', $property->id)
                ->withErrors(__('admin.gsc.message.oauth_no_refresh'));
        }

        $property->update([
            'auth_type' => 'oauth',
            'oauth_email' => $this->emailFromIdToken((string) $response->json('id_token', '')),
        ]);
        $this->storeSecret($property, $refreshToken, GscPropertySecret::KIND_OAUTH_REFRESH);

        return redirect()->route('admin.google-search-console.show', $property->id)
            ->with('message', __('admin.gsc.message.oauth_connected'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?GscProperty $property): array
    {
        return [
            'pageTitle' => __('admin.gsc.page_title'),
            'activeMenu' => 'google_search_console',
            'adminSiteName' => AdminWeb::siteName(),
            'property' => $property,
            'authTypes' => GscProperty::AUTH_TYPES,
            'schedules' => ['manual', 'daily', 'weekly'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProperty(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'site_url' => ['required', 'string', 'max:300'],
            'auth_type' => ['required', 'string', 'in:'.implode(',', GscProperty::AUTH_TYPES)],
            'schedule' => ['nullable', 'string', 'in:manual,daily,weekly'],
            'service_account_json' => ['nullable', 'string', 'max:8000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function maybeStoreServiceAccount(GscProperty $property, array $data): void
    {
        $json = trim((string) ($data['service_account_json'] ?? ''));
        if ($json === '' || $data['auth_type'] !== 'service_account') {
            return;
        }

        $this->storeSecret($property, $json, GscPropertySecret::KIND_SERVICE_ACCOUNT);
    }

    private function storeSecret(GscProperty $property, string $plain, string $kind): void
    {
        $property->secrets()->update(['status' => 'revoked']);
        $property->secrets()->create([
            'key_id' => 'gsc_'.Str::lower(Str::random(18)),
            'secret_kind' => $kind,
            'secret_ciphertext' => $this->apiKeyCrypto->encrypt($plain),
            'status' => 'active',
            'scopes' => [GscAuthResolver::SCOPE],
        ]);
    }

    private function oauthRedirectUri(): string
    {
        $configured = trim((string) config('geoflow.google_search_console.oauth_redirect_uri', ''));

        return $configured !== '' ? $configured : route('admin.google-search-console.oauth-callback');
    }

    /**
     * 从 id_token（JWT）解析登录邮箱，仅用于展示，不做签名校验。
     */
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
