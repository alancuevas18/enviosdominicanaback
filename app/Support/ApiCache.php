<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

final class ApiCache
{
    private const string VERSION_PREFIX = 'api-cache-version';

    public static function remember(
        string $namespace,
        string $key,
        DateTimeInterface|int|null $ttl,
        Closure $callback
    ): mixed {
        $cacheKey = sprintf('%s:v%s:%s', $namespace, self::version($namespace), $key);

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public static function bump(string $namespace): void
    {
        $versionKey = self::versionKey($namespace);

        if (Cache::add($versionKey, 1)) {
            return;
        }

        Cache::increment($versionKey);
    }

    /**
     * @param  array<int, string>  $namespaces
     */
    public static function bumpMany(array $namespaces): void
    {
        foreach ($namespaces as $namespace) {
            self::bump($namespace);
        }
    }

    private static function version(string $namespace): int
    {
        $versionKey = self::versionKey($namespace);

        Cache::add($versionKey, 1);

        return (int) Cache::get($versionKey, 1);
    }

    private static function versionKey(string $namespace): string
    {
        return self::VERSION_PREFIX . ':' . $namespace;
    }
}
