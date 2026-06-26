<?php

namespace App\Support\Site;

use App\Support\Tenancy\TenantContext;

class SiteThemeCatalog
{
    /**
     * @return array<int, array{id:string,name:string,version:string,description:string}>
     */
    public function all(): array
    {
        $themesRoot = resource_path('views/theme');
        if (! is_dir($themesRoot)) {
            return [];
        }

        $themes = [];
        $entries = scandir($themesRoot);
        if (! is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if ($entry === 'tenants') {
                array_push($themes, ...$this->tenantThemes($themesRoot.DIRECTORY_SEPARATOR.$entry));

                continue;
            }

            if (! preg_match('/^[a-zA-Z0-9_-]+$/', $entry)) {
                continue;
            }

            $themeDir = $themesRoot.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($themeDir)) {
                continue;
            }

            $manifestPath = $themeDir.DIRECTORY_SEPARATOR.'manifest.json';
            if (is_file($manifestPath)) {
                $manifestRaw = file_get_contents($manifestPath);
                if (! is_string($manifestRaw) || $manifestRaw === '') {
                    continue;
                }

                $manifest = json_decode($manifestRaw, true);
                if (! is_array($manifest)) {
                    continue;
                }

                $themes[] = [
                    'id' => (string) $entry,
                    'name' => (string) ($manifest['name'] ?? $entry),
                    'version' => (string) ($manifest['version'] ?? ''),
                    'description' => (string) ($manifest['description'] ?? ''),
                ];

                continue;
            }

            if (! is_file($themeDir.DIRECTORY_SEPARATOR.'home.blade.php')) {
                continue;
            }

            $themes[] = [
                'id' => (string) $entry,
                'name' => ucfirst(str_replace(['-', '_'], ' ', $entry)),
                'version' => '',
                'description' => '',
            ];
        }

        usort($themes, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $themes;
    }

    /**
     * @return array<int, array{id:string,name:string,version:string,description:string}>
     */
    private function tenantThemes(string $tenantsRoot): array
    {
        if (! is_dir($tenantsRoot)) {
            return [];
        }

        $currentTenantId = TenantContext::id();
        if ($currentTenantId === null) {
            return [];
        }

        $themes = [];
        $tenantEntries = scandir($tenantsRoot);
        if (! is_array($tenantEntries)) {
            return [];
        }

        foreach ($tenantEntries as $tenantEntry) {
            if (! preg_match('/^[0-9]+$/', (string) $tenantEntry)) {
                continue;
            }

            if ((int) $tenantEntry !== $currentTenantId) {
                continue;
            }

            $tenantThemeRoot = $tenantsRoot.DIRECTORY_SEPARATOR.$tenantEntry;
            $themeEntries = scandir($tenantThemeRoot);
            if (! is_array($themeEntries)) {
                continue;
            }

            foreach ($themeEntries as $themeEntry) {
                if (! preg_match('/^[a-zA-Z0-9_-]+$/', (string) $themeEntry)) {
                    continue;
                }

                $themeDir = $tenantThemeRoot.DIRECTORY_SEPARATOR.$themeEntry;
                if (! is_dir($themeDir)) {
                    continue;
                }

                $manifestPath = $themeDir.DIRECTORY_SEPARATOR.'manifest.json';
                if (! is_file($manifestPath)) {
                    continue;
                }

                $manifestRaw = file_get_contents($manifestPath);
                if (! is_string($manifestRaw) || $manifestRaw === '') {
                    continue;
                }

                $manifest = json_decode($manifestRaw, true);
                if (! is_array($manifest)) {
                    continue;
                }

                $themes[] = [
                    'id' => 'tenants/'.$tenantEntry.'/'.$themeEntry,
                    'name' => (string) ($manifest['name'] ?? $themeEntry),
                    'version' => (string) ($manifest['version'] ?? ''),
                    'description' => (string) ($manifest['description'] ?? ''),
                ];
            }
        }

        return $themes;
    }

    /**
     * @return array<int,string>
     */
    public function ids(): array
    {
        return array_map(static fn (array $theme): string => (string) $theme['id'], $this->all());
    }
}
