<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\GeoFlow\GeoArticleOptimizerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * 异步执行 GEO 一键优化（外挂式扩展）。
 *
 * AI 重写可能耗时较长，放到队列里跑，避免阻塞后台 HTTP 请求 / 触发 php-fpm 超时。
 * 用缓存标记「优化中」状态，供面板展示进度与失败原因。
 */
class OptimizeArticleGeoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $articleId) {}

    /** 「优化中」标记缓存键。 */
    public static function lockKey(int $articleId): string
    {
        return 'geo_optimizing:'.$articleId;
    }

    /** 最近一次优化失败原因缓存键。 */
    public static function errorKey(int $articleId): string
    {
        return 'geo_optimize_error:'.$articleId;
    }

    public function handle(GeoArticleOptimizerService $optimizer): void
    {
        try {
            $article = Article::query()->find($this->articleId);
            if ($article !== null) {
                $optimizer->optimize($article);
            }
            Cache::forget(self::errorKey($this->articleId));
        } catch (Throwable $e) {
            Cache::put(self::errorKey($this->articleId), $e->getMessage(), now()->addMinutes(10));
        } finally {
            Cache::forget(self::lockKey($this->articleId));
        }
    }
}
