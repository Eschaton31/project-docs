<?php

namespace Tests\Unit;

use App\Ai\ProseExtractor;
use App\Ai\ProseSnapshot;
use App\Ai\Scaffold\RScriptIndex;
use App\Ai\Scaffold\ScaffoldTemplate;
use App\Ai\Scaffold\ScaffoldValidator;
use App\Models\ProseSuggestion;
use Tests\TestCase;

class ScaffoldValidatorTest extends TestCase
{
    private int $ids = 0;

    private ProseSnapshot $document;

    private array $sources;

    protected function setUp(): void
    {
        parent::setUp();

        // B1 OVERVIEW > B2 Paragraph > B3 RichText; B4 USAGE > B5 Paragraph > B6 RichText
        $this->document = (new ProseExtractor)->extract([
            $this->node('PrimaryTopic', ['title' => 'OVERVIEW'], [
                $this->node('Paragraph', [], [$this->node('RichText', ['html' => 'The scripts build the monthly report.'])]),
            ]),
            $this->node('PrimaryTopic', ['title' => 'USAGE'], [
                $this->node('Paragraph', [], [$this->node('RichText', ['html' => 'Open main.R in RStudio.'])]),
            ]),
        ]);
        $this->sources = [
            ['ref' => 'F1', 'name' => 'main.R', 'text' => "library(dplyr)\nsource(\"R/clean.R\")\nraw <- read.csv(\"in.csv\")\nresult <- clean_data(raw)\n", 'entry' => true],
            ['ref' => 'F2', 'name' => 'R/clean.R', 'text' => "clean_data <- function(df) {\n  df |> filter(!is.na(id))\n}\n"],
        ];
    }

    public function test_a_new_section_is_placed_after_its_anchor_in_template_order(): void
    {
        $validator = $this->validator();
        [$row] = $validator->validate(['sections' => [$this->section('dependencies', 'B1', $this->dependencies(['dplyr']))]]);

        $this->assertSame(ProseSuggestion::SCAFFOLD, $row['kind']);
        $this->assertSame('dependencies', $row['rule']);
        $this->assertSame(ScaffoldTemplate::order('dependencies'), $row['order']);
        $this->assertSame([$this->document->blocks['B1']['id']], $row['block_ids']);
        $this->assertNull($row['parent_id']);
        $this->assertSame('after', $row['position']);
        $this->assertSame(['code'], $row['sources']);
        $this->assertSame([['file' => 'F1', 'from' => 1, 'to' => 1]], $row['citations']);
        $this->assertSame('DEPENDENCIES', $row['replacement'][0]['props']['title']);
        $this->assertSame([], $validator->dropped());
    }

    public function test_after_empty_puts_a_section_first(): void
    {
        [$row] = $this->validator()->validate(['sections' => [$this->section('overview', '', $this->topic('OVERVIEW'), ['run_notes'], [])]]);

        $this->assertSame([], $row['block_ids']);
    }

    /**
     * @return array<string, array{0: array, 1: string}>
     */
    public static function badSections(): array
    {
        return [
            'not a template section' => [['section' => 'history'], 'unknown section'],
            'script reference from the overview' => [['section' => 'script-reference'], 'script reference outside a script call'],
            'after a block that is not where sections go' => [['after' => 'B2'], 'after is not a block where sections go'],
            'not a topic' => [['blocks_json' => json_encode([['type' => 'Paragraph', 'children' => []]])], 'replacement breaks nesting rules'],
            'two topics' => [['blocks_json' => json_encode([['type' => 'PrimaryTopic', 'props' => ['title' => 'A']], ['type' => 'PrimaryTopic', 'props' => ['title' => 'B']]])], 'not one topic of the section level'],
            'unknown file cited' => [['citations' => [['file' => 'F9', 'from' => 1, 'to' => 1]]], 'cites an unknown file'],
            'lines past the end' => [['citations' => [['file' => 'F1', 'from' => 3, 'to' => 9]]], 'cites lines that are not in the file'],
            'code without citations' => [['citations' => []], 'code without citations'],
            'unknown source' => [['sources' => ['internet']], 'unknown sources'],
        ];
    }

    public function test_bad_sections_are_dropped(): void
    {
        foreach (self::badSections() as $case => [$override, $reason]) {
            $validator = $this->validator();
            $rows = $validator->validate(['sections' => [[...$this->section('dependencies', 'B1', $this->dependencies(['dplyr'])), ...$override]]]);

            $this->assertSame([], $rows, $case);
            $this->assertSame([$reason => 1], $validator->dropped(), $case);
        }
    }

    public function test_a_section_is_suggested_once(): void
    {
        $validator = $this->validator();
        $rows = $validator->validate(['sections' => [
            $this->section('dependencies', 'B1', $this->dependencies(['dplyr'])),
            $this->section('dependencies', 'B4', $this->dependencies(['dplyr'])),
        ]]);

        $this->assertCount(1, $rows);
        $this->assertSame(['section suggested twice' => 1], $validator->dropped());
    }

    public function test_citations_without_code_add_it_to_the_sources(): void
    {
        [$row] = $this->validator()->validate(['sections' => [$this->section('dependencies', 'B1', $this->dependencies(['dplyr']), ['document'])]]);

        $this->assertSame(['document', 'code'], $row['sources']);
    }

    public function test_r_code_must_be_an_exact_excerpt_of_a_cited_script(): void
    {
        $code = fn (string $source) => $this->topic('SCRIPT FLOW', [['type' => 'CodeBlock', 'props' => ['lang' => 'r', 'code' => $source]]]);
        $cite = [['file' => 'F2', 'from' => 1, 'to' => 3]];

        // indentation and spacing don't matter
        $this->assertCount(1, $this->validator()->validate(['sections' => [$this->section('script-flow', 'B1', $code("clean_data <- function(df) {\n    df |>  filter(!is.na(id))\n}"), citations: $cite)]]));

        $validator = $this->validator();
        $this->assertSame([], $validator->validate(['sections' => [$this->section('script-flow', 'B1', $code("clean_data <- function(df) {\n  dplyr::filter(df, !is.na(id))\n}"), citations: $cite)]]));
        $this->assertSame(['R code that is not in the scripts' => 1], $validator->dropped());

        // in a script, but not one it cites
        $validator = $this->validator();
        $this->assertSame([], $validator->validate(['sections' => [$this->section('script-flow', 'B1', $code('library(dplyr)'), citations: $cite)]]));

        // other languages aren't checked
        $this->assertCount(1, $this->validator()->validate(['sections' => [$this->section('script-flow', 'B1', $this->topic('SCRIPT FLOW', [['type' => 'CodeBlock', 'props' => ['lang' => 'bash', 'code' => 'Rscript main.R']]]), citations: $cite)]]));
    }

    public function test_dependencies_name_only_packages_the_scripts_use(): void
    {
        $validator = $this->validator();

        $this->assertSame([], $validator->validate(['sections' => [$this->section('dependencies', 'B1', $this->dependencies(['dplyr', 'ggplot2']))]]));
        $this->assertSame(['package the scripts do not use' => 1], $validator->dropped());
    }

    public function test_file_trees_name_only_uploaded_scripts(): void
    {
        $tree = fn (string $name) => $this->topic('PROJECT STRUCTURE', [['type' => 'FileTree', 'props' => ['list' => [
            ['type' => 'file', 'name' => 'main.R'],
            ['type' => 'folder', 'name' => 'R', 'children' => [['type' => 'file', 'name' => $name]]],
        ]]]]);

        $this->assertCount(1, $this->validator()->validate(['sections' => [$this->section('project-structure', 'B1', $tree('clean.R'))]]));

        $validator = $this->validator();
        $this->assertSame([], $validator->validate(['sections' => [$this->section('project-structure', 'B1', $tree('helpers.R'))]]));
        $this->assertSame(['file tree names a file that was not uploaded' => 1], $validator->dropped());
    }

    public function test_additions_to_existing_sections_are_expansions(): void
    {
        [$row] = $this->validator()->validate(['expand' => [[
            'section' => 'overview', 'anchor' => 'B1', 'position' => 'end',
            'blocks_json' => json_encode([['type' => 'Placeholder', 'props' => ['text' => 'Who uses the report?']]]),
            'sources' => ['document'], 'citations' => [], 'rationale' => 'The overview does not say who uses the report.',
        ]]]);

        $this->assertSame(ProseSuggestion::EXPAND, $row['kind']);
        $this->assertSame('overview', $row['rule']);
        $this->assertSame([$this->document->blocks['B1']['id']], $row['block_ids']);
        $this->assertSame($this->document->blocks['B1']['id'], $row['parent_id']);
        $this->assertSame('end', $row['position']);
    }

    public function test_script_topics_are_gathered_into_one_script_reference_next_to_the_closest_new_section(): void
    {
        $validator = $this->validator();
        $validator->validate(['sections' => [
            $this->section('dependencies', 'B1', $this->dependencies(['dplyr'])),
            $this->section('troubleshooting', 'B4', $this->topic('TROUBLESHOOTING'), ['run_notes'], []),
        ]]);
        $this->assertSame([], $validator->validate(['script_topics' => [
            $this->scriptTopic('F1', 'main.R'),
            $this->scriptTopic('F2', 'R/clean.R'),
        ]]));

        $row = $validator->scriptReference();
        $this->assertSame(ProseSuggestion::SCAFFOLD, $row['kind']);
        $this->assertSame(ScaffoldTemplate::SCRIPT_REFERENCE, $row['rule']);
        $this->assertSame('SCRIPT REFERENCE', $row['replacement'][0]['props']['title']);
        $this->assertSame(['main.R', 'R/clean.R'], array_map(fn ($topic) => $topic['props']['title'], $row['replacement'][0]['children']));
        // Dependencies comes before it in the template and went after B1, so it goes there too
        $this->assertSame([$this->document->blocks['B1']['id']], $row['block_ids']);
        $this->assertCount(2, $row['citations']);
    }

    public function test_with_no_new_sections_the_script_reference_goes_last(): void
    {
        $validator = $this->validator();
        $validator->validate(['script_topics' => [$this->scriptTopic('F1', 'main.R')]]);

        $this->assertSame([$this->document->blocks['B4']['id']], $validator->scriptReference()['block_ids']);
    }

    public function test_a_script_topic_into_an_existing_topic_is_an_expansion_at_its_end(): void
    {
        $validator = $this->validator();
        [$row] = $validator->validate(['script_topics' => [[...$this->scriptTopic('F1', 'main.R'), 'into' => 'B4']]]);

        $this->assertSame(ProseSuggestion::EXPAND, $row['kind']);
        $this->assertSame('end', $row['position']);
        $this->assertSame($this->document->blocks['B4']['id'], $row['parent_id']);
        $this->assertNull($validator->scriptReference());
    }

    public function test_inside_a_topic_sections_are_one_level_down(): void
    {
        $validator = new ScaffoldValidator($this->document, 'B4', $this->sources, RScriptIndex::build($this->sources));

        $this->assertSame([], $validator->validate(['sections' => [$this->section('dependencies', '', $this->dependencies(['dplyr']))]]));
        [$row] = $validator->validate(['sections' => [$this->section('dependencies', 'B5', [
            'type' => 'SecondaryTopic', 'props' => ['title' => 'Dependencies'],
            'children' => [['type' => 'ItemDescriptionTable', 'props' => ['itemName' => 'Package', 'descriptionName' => 'Used for', 'data' => [['item' => 'dplyr', 'description' => 'Filtering']]]]],
        ])]]);

        $this->assertSame($this->document->blocks['B4']['id'], $row['parent_id']);
        $this->assertSame([$this->document->blocks['B5']['id']], $row['block_ids']);
    }

    private function validator(): ScaffoldValidator
    {
        return new ScaffoldValidator($this->document, null, $this->sources, RScriptIndex::build($this->sources));
    }

    private function section(string $key, string $after, array $node, array $sources = ['code'], ?array $citations = null): array
    {
        return [
            'section' => $key, 'after' => $after, 'blocks_json' => json_encode([$node]),
            'sources' => $sources, 'citations' => $citations ?? [['file' => 'F1', 'from' => 1, 'to' => 1]],
            'rationale' => 'The document has no such section.',
        ];
    }

    private function scriptTopic(string $file, string $title): array
    {
        return [
            'file' => $file, 'into' => '',
            'blocks_json' => json_encode([['type' => 'SecondaryTopic', 'props' => ['title' => $title], 'children' => [
                ['type' => 'Paragraph', 'children' => [['type' => 'RichText', 'props' => ['html' => 'What it does.']]]],
            ]]]),
            'sources' => ['code'], 'citations' => [['file' => $file, 'from' => 1, 'to' => 2]], 'rationale' => 'Documents the script.',
        ];
    }

    private function dependencies(array $packages): array
    {
        return $this->topic('DEPENDENCIES', [[
            'type' => 'ItemDescriptionTable',
            'props' => ['itemName' => 'Package', 'descriptionName' => 'Used for', 'mono' => true, 'data' => array_map(fn ($package) => ['item' => $package, 'description' => 'Data work'], $packages)],
        ]]);
    }

    private function topic(string $title, array $children = []): array
    {
        return ['type' => 'PrimaryTopic', 'props' => ['title' => $title], 'children' => $children ?: [['type' => 'Placeholder', 'props' => ['text' => 'to be written']]]];
    }

    private function node(string $type, array $props, array $children = [], int $version = 1): array
    {
        return ['id' => sprintf('00000000-0000-0000-0000-%012d', ++$this->ids), 'type' => $type, 'props' => $props, 'options' => [], 'version' => $version, 'children' => $children];
    }
}
