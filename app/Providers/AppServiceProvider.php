<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureHealthChecks();
    }

    /**
     * Configure the rate limiters for the application.
     */
    private function configureRateLimiting(): void
    {
        // Default API rate limiter - 60 requests per minute
        RateLimiter::for('api', fn(Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Auth endpoints - more restrictive (prevent brute force)
        RateLimiter::for('auth', fn(Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Authenticated user requests - higher limit
        RateLimiter::for('authenticated', fn(Request $request) => $request->user()
            ? Limit::perMinute(120)->by($request->user()->id)
            : Limit::perMinute(60)->by($request->ip()));
    }

    /**
     * Configure health checks for infrastructure observability.
     */
    private function configureHealthChecks(): void
    {
        $enableQueueCheck = filter_var((string) env('HEALTH_ENABLE_QUEUE_CHECK', false), FILTER_VALIDATE_BOOL);
        $enableScheduleCheck = filter_var((string) env('HEALTH_ENABLE_SCHEDULE_CHECK', false), FILTER_VALIDATE_BOOL);
        $enableHorizonCheck = filter_var((string) env('HEALTH_ENABLE_HORIZON_CHECK', false), FILTER_VALIDATE_BOOL);

        $checks = [
            DatabaseCheck::new(),
            CacheCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage((int) env('HEALTH_DISK_WARN_PERCENT', 80))
                ->failWhenUsedSpaceIsAbovePercentage((int) env('HEALTH_DISK_FAIL_PERCENT', 90)),
        ];

        if ($enableQueueCheck) {
            $checks[] = QueueCheck::new()->failWhenHealthJobTakesLongerThanMinutes((int) env('HEALTH_QUEUE_MAX_AGE_MINUTES', 5));
        }

        if ($enableScheduleCheck) {
            $checks[] = ScheduleCheck::new()->heartbeatMaxAgeInMinutes((int) env('HEALTH_SCHEDULE_MAX_AGE_MINUTES', 2));
        }

        if ($enableHorizonCheck && (string) config('queue.default') === 'redis') {
            $checks[] = HorizonCheck::new();
        }

        if (app()->isProduction()) {
            $checks[] = DebugModeCheck::new()->expectedToBe(false);
            $checks[] = EnvironmentCheck::new()->expectEnvironment('production');
        }

        Health::checks($checks);
    }
}
