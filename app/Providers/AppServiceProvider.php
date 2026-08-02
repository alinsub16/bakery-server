<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // General ceiling for any authenticated API call — generous, just
        // a backstop against runaway scripts or a compromised token being
        // used for bulk scraping.
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Tighter limit specifically for writes (POST/PUT/PATCH) — these
        // are the operations that actually change data, so they deserve
        // a stricter ceiling than read-only browsing.
        RateLimiter::for('api-writes', function ($request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Login stays separate and stricter, keyed by IP + email together
        // so one attacker can't lock out a legitimate user's email by
        // spamming failed attempts against it from many IPs, and vice versa.
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });
    }
}