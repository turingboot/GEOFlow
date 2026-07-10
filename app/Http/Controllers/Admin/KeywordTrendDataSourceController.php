<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeoFlow\KeywordTrend\KeywordTrendDataSourceCredentialService;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KeywordTrendDataSourceController extends Controller
{
    public function __construct(private readonly KeywordTrendDataSourceCredentialService $credentials) {}

    public function edit(): View
    {
        return view('admin.keyword-trend-data-source.edit', [
            'pageTitle' => __('admin.keyword_data_source.page_title'),
            'activeMenu' => 'ai_config',
            'adminSiteName' => AdminWeb::siteName(),
            'hasApiKey' => $this->credentials->hasSerpApiApiKey(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $apiKey = trim((string) ($payload['api_key'] ?? ''));
        if ($apiKey === '') {
            if (! $this->credentials->hasSerpApiApiKey()) {
                return back()
                    ->withInput()
                    ->with('api_key_error', __('admin.keyword_data_source.error.api_key_required'));
            }

            return redirect()->route('admin.keyword-data-source.edit')
                ->with('message', __('admin.keyword_data_source.message.saved'));
        }

        $this->credentials->saveSerpApiApiKey($apiKey);

        return redirect()->route('admin.keyword-data-source.edit')
            ->with('message', __('admin.keyword_data_source.message.saved'));
    }
}
