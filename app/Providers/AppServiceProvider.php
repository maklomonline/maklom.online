<?php

namespace App\Providers;

use App\Services\KataGoService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // KataGoService เป็น singleton เพื่อให้ KataGo process ถูกสร้างครั้งเดียว
        // และนำกลับมาใช้ซ้ำระหว่าง queue jobs (ลดเวลาโหลด neural network)
        $this->app->singleton(KataGoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
