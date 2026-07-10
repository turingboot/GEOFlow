<?php

namespace App\Support;

/**
 * 后台入口路径管理。
 *
 * 后台入口固定为 /geo，不再允许通过网站设置或环境变量动态切换。
 */
final class AdminBasePathManager
{
    public const DEFAULT_PATH = 'geo';

    /**
     * @return array<int, string>
     */
    public static function reservedSegments(): array
    {
        return [
            'api',
            'archive',
            'article',
            'assets',
            'build',
            'category',
            'css',
            'favicon.ico',
            'images',
            'js',
            'storage',
            'vendor',
        ];
    }

    public static function normalize(string $path): string
    {
        return self::DEFAULT_PATH;
    }

    public static function persist(string $path): string
    {
        return self::DEFAULT_PATH;
    }
}
