<?php

namespace App\Providers;

use App\Ai\Reviewers\ApiReviewer;
use App\Ai\Reviewers\ChunkReviewer;
use App\Ai\Reviewers\ClaudeCodeReviewer;
use App\Models\User;
use App\Pdf\BrowsershotRenderer;
use App\Pdf\PdfRenderer;
use App\Support\DevServerVite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, fn () => new BrowsershotRenderer(config('services.pdf')));
        $this->app->singleton(Vite::class, DevServerVite::class);
        $this->app->bind(ChunkReviewer::class, fn () => config('ai-review.runner') === 'api' ? new ApiReviewer : new ClaudeCodeReviewer);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // each review makes paid model calls
        RateLimiter::for('ai-review', fn (Request $request) => Limit::perHour(config('ai-review.rate_limit_per_hour'))->by($request->user()?->id ?: $request->ip()));

        if ($this->app->runningConsoleCommand('dev')) {
            $this->serveOnHostIp();
            // AI prose reviews run on their own queue (config/queue.php "ai-review")
            DevCommands::artisan('queue:listen ai-review --queue=ai-review --tries=1 --timeout=0', 'ai-queue');
        }
    }

    /**
     * Runs `composer dev` on every network interface instead of only localhost, so other devices can open it.
     * The servers listen on 0.0.0.0 while URLs advertise this machine's LAN IP (browsers can't connect to 0.0.0.0).
     * The URLs are handed to every dev process as environment variables, which take precedence over .env.
     */
    private function serveOnHostIp(): void
    {
        $ip = $this->hostIp();
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: 8000;

        // the browser reaches Reverb wherever it opened the page (resources/js/builder/realtime.js)
        foreach (['APP_URL' => "http://{$ip}:{$port}", 'REVERB_HOST' => $ip, 'VITE_REVERB_FROM_PAGE' => 'true'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        DevCommands::artisan("serve --host=0.0.0.0 --port={$port}", 'server');
        DevCommands::node('dev -- --host 0.0.0.0', 'vite');
    }

    /**
     * The address of the network interface that routes to the internet. Connecting a UDP socket sends no packets.
     */
    private function hostIp(): string
    {
        $socket = @stream_socket_client('udp://8.8.8.8:53');
        $address = $socket ? stream_socket_get_name($socket, false) : false;

        return $address ? Str::beforeLast($address, ':') : gethostbyname(gethostname());
    }
}
