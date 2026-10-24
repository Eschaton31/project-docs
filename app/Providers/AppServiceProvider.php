<?php

namespace App\Providers;

use App\Models\User;
use App\Pdf\BrowsershotRenderer;
use App\Pdf\PdfRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
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
     * Runs `composer dev` on this machine's LAN IP instead of localhost, so other devices can open it.
     * The URLs are handed to every dev process as environment variables, which take precedence over .env.
     */
    private function serveOnHostIp(): void
    {
        $ip = $this->hostIp();
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: 8000;

        foreach (['APP_URL' => "http://{$ip}:{$port}", 'REVERB_HOST' => $ip, 'VITE_REVERB_HOST' => $ip] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        DevCommands::artisan("serve --host={$ip} --port={$port}", 'server');
        DevCommands::node("dev -- --host {$ip}", 'vite');
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
