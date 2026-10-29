<?php

namespace Tests\Unit;

use App\Ai\ProseExtractor;
use App\Ai\Scaffold\RScriptIndex;
use App\Ai\Scaffold\ScaffoldPrompt;
use Tests\TestCase;

class ScaffoldPromptTest extends TestCase
{
    public function test_small_scripts_take_an_overview_call_and_one_script_call(): void
    {
        $sources = [
            ['ref' => 'F1', 'name' => 'clean.R', 'text' => "x <- 1\n"],
            ['ref' => 'F2', 'name' => 'main.R', 'text' => "source(\"clean.R\")\n", 'entry' => true],
        ];

        $calls = ScaffoldPrompt::calls($sources, RScriptIndex::build($sources), 1000);

        $this->assertSame([
            ['task' => ScaffoldPrompt::OVERVIEW, 'files' => [['ref' => 'F2', 'from' => 1, 'to' => 1], ['ref' => 'F1', 'from' => 1, 'to' => 1]]],
            ['task' => ScaffoldPrompt::SCRIPTS, 'files' => [['ref' => 'F2', 'from' => 1, 'to' => 1], ['ref' => 'F1', 'from' => 1, 'to' => 1]]],
        ], $calls);
    }

    public function test_scripts_are_grouped_up_to_the_limit(): void
    {
        $sources = array_map(fn ($n) => ['ref' => "F{$n}", 'name' => "s{$n}.R", 'text' => str_repeat("x <- 1\n", 10)], [1, 2, 3]);

        // 70 characters each, 150 per call: two scripts, then one
        $calls = ScaffoldPrompt::calls($sources, RScriptIndex::build($sources), 150);

        $this->assertSame([['F1', 'F2'], ['F3']], array_map(fn ($call) => array_column($call['files'], 'ref'), array_slice($calls, 1)));
    }

    public function test_a_large_script_is_split_before_a_function_and_the_overview_sees_the_start_of_the_others(): void
    {
        $big = implode("\n", [
            'setup <- 1',                       // 1
            'load_data <- function(path) {',    // 2
            '  read.csv(path)',                 // 3
            '}',                                // 4
            'clean_data <- function(df) {',     // 5
            '  df[!is.na(df$id), ]',            // 6
            '}',                                // 7
        ])."\n";
        $sources = [
            ['ref' => 'F1', 'name' => 'main.R', 'text' => $big, 'entry' => true],
            ['ref' => 'F2', 'name' => 'other.R', 'text' => str_repeat("y <- 2\n", 70)],
        ];

        $calls = ScaffoldPrompt::calls($sources, RScriptIndex::build($sources), 100);

        // the overview: the start of main.R, cut before a function, and the first 60 lines of other.R
        $this->assertSame([['ref' => 'F1', 'from' => 1, 'to' => 4], ['ref' => 'F2', 'from' => 1, 'to' => 60]], $calls[0]['files']);
        $this->assertSame([['ref' => 'F1', 'from' => 1, 'to' => 4]], $calls[1]['files']);
        $this->assertSame([['ref' => 'F1', 'from' => 5, 'to' => 7]], $calls[2]['files']);
    }

    public function test_the_prompt_says_where_new_sections_go_and_numbers_the_source(): void
    {
        $sources = [['ref' => 'F1', 'name' => 'main.R', 'text' => "library(dplyr)\nx <- 1\n", 'entry' => true]];
        $index = RScriptIndex::build($sources);
        $document = (new ProseExtractor)->extract([
            ['id' => 'a', 'type' => 'PrimaryTopic', 'props' => ['title' => 'USAGE'], 'options' => [], 'version' => 1, 'children' => []],
        ]);

        $prompt = ScaffoldPrompt::render($document, 'B1', $sources, $index, null, ScaffoldPrompt::calls($sources, $index, 1000)[1], 1000);

        $this->assertStringContainsString('Write the script-reference topic for: F1 main.R.', $prompt);
        $this->assertStringContainsString('A script\'s topic is a TertiaryTopic.', $prompt);
        $this->assertStringContainsString('New sections go inside B1 (PrimaryTopic), as SecondaryTopic nodes. There is nothing there yet.', $prompt);
        $this->assertStringContainsString('No run notes were given', $prompt);
        $this->assertStringContainsString("1| library(dplyr)\n2| x <- 1", $prompt);
    }
}
