<?php

namespace App\Services\GeoFlow\KeywordTrend;

use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KeywordTrend;
use App\Models\KeywordTrendSource;
use Illuminate\Support\Facades\DB;

/**
 * Imports trend keywords into a source's target KeywordLibrary, de-duping against
 * existing keywords (same logic as MaterialLibraryService::createKeywordItem) and
 * keeping the library's keyword_count in sync.
 */
class KeywordTrendImportService
{
    /**
     * @param  iterable<KeywordTrend>  $trends
     * @return array{imported: int, skipped: int, library_id: int|null}
     */
    public function import(KeywordTrendSource $source, iterable $trends): array
    {
        $libraryId = (int) ($source->target_keyword_library_id ?? 0);
        if ($libraryId <= 0 || ! KeywordLibrary::query()->whereKey($libraryId)->exists()) {
            return ['imported' => 0, 'skipped' => 0, 'library_id' => null];
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($libraryId, $trends, &$imported, &$skipped): void {
            foreach ($trends as $trend) {
                $keyword = trim((string) $trend->keyword);
                if ($keyword === '') {
                    $skipped++;

                    continue;
                }

                $existing = Keyword::query()
                    ->where('library_id', $libraryId)
                    ->where('keyword', $keyword)
                    ->first();

                if ($existing !== null) {
                    $trend->update(['imported' => true, 'keyword_id' => $existing->id]);
                    $skipped++;

                    continue;
                }

                $row = Keyword::query()->create([
                    'library_id' => $libraryId,
                    'keyword' => $keyword,
                    'used_count' => 0,
                    'usage_count' => 0,
                ]);
                $trend->update(['imported' => true, 'keyword_id' => $row->id]);
                $imported++;
            }

            $count = Keyword::query()->where('library_id', $libraryId)->count();
            KeywordLibrary::query()->whereKey($libraryId)->update(['keyword_count' => $count]);
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'library_id' => $libraryId];
    }
}
