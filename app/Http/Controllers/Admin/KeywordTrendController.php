<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchKeywordTrendsJob;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrend;
use App\Models\KeywordTrendSource;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendImportService;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KeywordTrendController extends Controller
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly KeywordTrendImportService $importer,
    ) {}

    public function index(): View
    {
        $sources = KeywordTrendSource::query()
            ->with(['latestSnapshot', 'targetLibrary'])
            ->orderByDesc('id')
            ->get();

        return view('admin.keyword-trends.index', [
            'pageTitle' => __('admin.keyword_trends.page_title'),
            'activeMenu' => 'keyword_trends',
            'adminSiteName' => AdminWeb::siteName(),
            'sources' => $sources,
            'stats' => [
                'total' => $sources->count(),
                'active' => $sources->where('status', 'active')->count(),
                'auto' => $sources->where('auto_import', true)->count(),
                'imported' => (int) KeywordTrend::query()->where('imported', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.keyword-trends.create', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSource($request);

        $source = KeywordTrendSource::query()->create($this->mapAttributes($data) + [
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        if (trim((string) ($data['api_key'] ?? '')) !== '') {
            $this->storeSecret($source, (string) $data['api_key']);
        }

        return redirect()->route('admin.keyword-trends.show', $source->id)
            ->with('message', __('admin.keyword_trends.message.created'));
    }

    public function edit(int $sourceId): View|RedirectResponse
    {
        $source = KeywordTrendSource::query()->whereKey($sourceId)->first();
        if ($source === null) {
            return redirect()->route('admin.keyword-trends.index')->withErrors(__('admin.keyword_trends.message.not_found'));
        }

        return view('admin.keyword-trends.edit', $this->formData($source));
    }

    public function update(Request $request, int $sourceId): RedirectResponse
    {
        $source = KeywordTrendSource::query()->whereKey($sourceId)->first();
        if ($source === null) {
            return redirect()->route('admin.keyword-trends.index')->withErrors(__('admin.keyword_trends.message.not_found'));
        }

        $data = $this->validateSource($request);
        $source->update($this->mapAttributes($data));

        if (trim((string) ($data['api_key'] ?? '')) !== '') {
            $this->storeSecret($source, (string) $data['api_key']);
        }

        return redirect()->route('admin.keyword-trends.show', $source->id)
            ->with('message', __('admin.keyword_trends.message.updated'));
    }

    public function show(int $sourceId): View|RedirectResponse
    {
        $source = KeywordTrendSource::query()
            ->with(['latestSnapshot', 'targetLibrary', 'activeSecret'])
            ->whereKey($sourceId)
            ->first();
        if ($source === null) {
            return redirect()->route('admin.keyword-trends.index')->withErrors(__('admin.keyword_trends.message.not_found'));
        }

        $snapshot = $source->latestSnapshot;
        $trends = $snapshot
            ? KeywordTrend::query()->where('keyword_trend_snapshot_id', $snapshot->id)->orderByDesc('heat')->get()
            : collect();

        return view('admin.keyword-trends.show', [
            'pageTitle' => __('admin.keyword_trends.page_title'),
            'activeMenu' => 'keyword_trends',
            'adminSiteName' => AdminWeb::siteName(),
            'source' => $source,
            'snapshot' => $snapshot,
            'trends' => $trends,
            'revealedSecret' => session('keyword_trend_secret'),
        ]);
    }

    public function fetch(int $sourceId): RedirectResponse
    {
        $source = KeywordTrendSource::query()->whereKey($sourceId)->first();
        if ($source === null) {
            return redirect()->route('admin.keyword-trends.index')->withErrors(__('admin.keyword_trends.message.not_found'));
        }

        FetchKeywordTrendsJob::dispatch((int) $source->id)->onQueue('trends');

        return redirect()->route('admin.keyword-trends.show', $source->id)
            ->with('message', __('admin.keyword_trends.message.fetch_queued'));
    }

    public function import(int $sourceId): RedirectResponse
    {
        $source = KeywordTrendSource::query()->with('latestSnapshot')->whereKey($sourceId)->first();
        if ($source === null) {
            return redirect()->route('admin.keyword-trends.index')->withErrors(__('admin.keyword_trends.message.not_found'));
        }
        if ($source->target_keyword_library_id === null) {
            return redirect()->route('admin.keyword-trends.show', $source->id)
                ->withErrors(__('admin.keyword_trends.message.no_library'));
        }

        $snapshot = $source->latestSnapshot;
        $trends = $snapshot ? $snapshot->trends()->get() : collect();
        $result = $this->importer->import($source, $trends);

        return redirect()->route('admin.keyword-trends.show', $source->id)
            ->with('message', __('admin.keyword_trends.message.imported', ['count' => $result['imported']]));
    }

    public function revealSecret(Request $request, int $sourceId): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin || ! method_exists($admin, 'isSuperAdmin') || ! $admin->isSuperAdmin()) {
            abort(403);
        }

        $source = KeywordTrendSource::query()->with('activeSecret')->whereKey($sourceId)->first();
        if ($source === null) {
            return redirect()->route('admin.keyword-trends.index')->withErrors(__('admin.keyword_trends.message.not_found'));
        }

        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('password'), (string) $admin->password)) {
            return redirect()->route('admin.keyword-trends.show', $source->id)
                ->withErrors(['password' => __('admin.keyword_trends.message.bad_password')]);
        }

        $secret = $source->activeSecret;
        $plain = $secret ? $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext) : '';

        return redirect()->route('admin.keyword-trends.show', $source->id)
            ->with('keyword_trend_secret', $plain);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?KeywordTrendSource $source): array
    {
        return [
            'pageTitle' => __('admin.keyword_trends.page_title'),
            'activeMenu' => 'keyword_trends',
            'adminSiteName' => AdminWeb::siteName(),
            'source' => $source,
            'providers' => KeywordTrendSource::PROVIDERS,
            'libraries' => KeywordLibrary::query()->orderBy('name')->get(['id', 'name']),
            'schedules' => ['manual', 'hourly', 'daily', 'weekly'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSource(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'in:'.implode(',', KeywordTrendSource::PROVIDERS)],
            'category' => ['required', 'string', 'max:160'],
            'seed_keywords' => ['nullable', 'string', 'max:2000'],
            'region' => ['nullable', 'string', 'max:16'],
            'language' => ['nullable', 'string', 'max:16'],
            'timeframe' => ['nullable', 'string', 'max:32'],
            'heat_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
            'top_n' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'target_keyword_library_id' => ['nullable', 'integer', 'exists:keyword_libraries,id'],
            'auto_import' => ['nullable', 'boolean'],
            'schedule' => ['nullable', 'string', 'in:manual,hourly,daily,weekly'],
            'dataforseo_login' => ['nullable', 'string', 'max:160'],
            'location_name' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapAttributes(array $data): array
    {
        $seeds = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', (string) ($data['seed_keywords'] ?? '')) ?: []),
            static fn (string $s): bool => $s !== '',
        ));

        return [
            'name' => $data['name'],
            'provider' => $data['provider'],
            'category' => $data['category'],
            'seed_keywords' => $seeds,
            'region' => $data['region'] ?? config('geoflow.keyword_trends.default_region', 'US'),
            'language' => $data['language'] ?? config('geoflow.keyword_trends.default_language', 'en'),
            'timeframe' => $data['timeframe'] ?? config('geoflow.keyword_trends.default_timeframe', 'past_month'),
            'heat_threshold' => (int) ($data['heat_threshold'] ?? config('geoflow.keyword_trends.heat_threshold', 60)),
            'top_n' => (int) ($data['top_n'] ?? config('geoflow.keyword_trends.top_n', 50)),
            'target_keyword_library_id' => $data['target_keyword_library_id'] ?? null,
            'auto_import' => (bool) ($data['auto_import'] ?? false),
            'schedule' => $data['schedule'] ?? 'manual',
            'config' => array_filter([
                'login' => $data['dataforseo_login'] ?? null,
                'location_name' => $data['location_name'] ?? null,
            ], static fn ($v): bool => $v !== null && $v !== ''),
            'status' => 'active',
        ];
    }

    private function storeSecret(KeywordTrendSource $source, string $apiKey): void
    {
        $source->secrets()->update(['status' => 'revoked']);
        $source->secrets()->create([
            'key_id' => 'kts_'.Str::lower(Str::random(18)),
            'secret_ciphertext' => $this->apiKeyCrypto->encrypt($apiKey),
            'status' => 'active',
            'scopes' => ['trend.fetch'],
        ]);
    }
}
