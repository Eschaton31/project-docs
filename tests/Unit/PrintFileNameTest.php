<?php

namespace Tests\Unit;

use App\Support\PrintFileName;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PrintFileNameTest extends TestCase
{
    public function test_fills_date_fns_patterns_like_the_editor_does(): void
    {
        $date = Carbon::create(2026, 10, 21, 9, 5, 7);

        $this->assertSame('oh-pipeline-tech-manual_20261021', PrintFileName::make('oh-pipeline-tech-manual_{yyyyMMdd}', $date));
        $this->assertSame('report 2026-10-21 0905', PrintFileName::make('report {yyyy-MM-dd HHmm}', $date));
        $this->assertSame('plan-v2', PrintFileName::make('plan-v2', $date));
    }

    public function test_keeps_the_name_safe_for_a_file_system(): void
    {
        $this->assertSame('a-b-c', PrintFileName::make('a/b:c'));
        $this->assertSame('document', PrintFileName::make('  '));
    }
}
