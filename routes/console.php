<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// R scripts uploaded for scaffolds are kept for config('ai-review.scaffold.retention_days')
Schedule::command('prose-review:prune-sources')->daily()->withoutOverlapping();
