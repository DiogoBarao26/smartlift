<?php

namespace App\Providers;

use App\Database\InMemorySqlite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
    public function boot(InMemorySqlite $memory): void
    {
        $memory->boot($this->app);
    }
}
