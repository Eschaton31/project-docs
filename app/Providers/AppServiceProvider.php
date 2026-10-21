<?php

namespace App\Providers;

use App\Models\User;
use App\Pdf\BrowsershotRenderer;
use App\Pdf\PdfRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, fn () => new BrowsershotRenderer(config('services.pdf')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());
    }
}
