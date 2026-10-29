<?php

namespace Tests\Unit;

use App\Ai\Scaffold\RScriptIndex;
use PHPUnit\Framework\TestCase;

class RScriptIndexTest extends TestCase
{
    public function test_packages_from_every_way_of_loading_them(): void
    {
        $index = $this->index(<<<'R'
        library(dplyr)
        library("readxl")
        require(tidyr)
        suppressPackageStartupMessages(library(lubridate))
        requireNamespace("janitor", quietly = TRUE)
        pacman::p_load(odbc, "DBI")
        x <- stringr::str_trim(y)
        z <- data.table:::fast(y)
        library(pkg, character.only = TRUE)
        R);

        $this->assertSame(['data.table', 'DBI', 'dplyr', 'janitor', 'lubridate', 'odbc', 'pacman', 'readxl', 'stringr', 'tidyr'], $index['packages']);
    }

    public function test_lookalikes_in_comments_and_strings_are_ignored(): void
    {
        $index = $this->index(<<<'R'
        # library(foo)
        msg <- "source('x.R') and library(bar) and baz::qux"
        cat('read.csv("nope.csv")') # write.csv(x, "nope.csv")
        R);

        $this->assertSame([], $index['packages']);
        $this->assertSame([], $index['files'][0]['sources']);
        $this->assertSame([], $index['files'][0]['reads']);
        $this->assertSame([], $index['files'][0]['writes']);
    }

    public function test_functions_with_their_lines_arguments_and_comments(): void
    {
        $index = $this->index(<<<'R'
        #' Cleans the monthly export.
        #' @param df the raw data
        clean_data <- function(df, month = "2026-09") {
          df |>
            filter(!is.na(id))
        }

        square = function(x) x^2
        R);

        $this->assertSame([
            ['name' => 'clean_data', 'args' => 'df, month = "2026-09"', 'from' => 3, 'to' => 6, 'doc' => "Cleans the monthly export.\n@param df the raw data"],
            ['name' => 'square', 'args' => 'x', 'from' => 8, 'to' => 8, 'doc' => ''],
        ], $index['files'][0]['functions']);
    }

    public function test_reads_writes_and_messages(): void
    {
        $file = $this->index(<<<'R'
        raw <- read_excel("\\\\share\\in\\export.xlsx", sheet = 1)
        ids <- DBI::dbGetQuery(con, "SELECT id, name FROM customers")
        if (nrow(raw) == 0) stop("The export is empty: check the month.")
        writexl::write_xlsx(raw, path = "report.xlsx")
        saveRDS(raw, file.path(out_dir, "raw.rds"))
        R)['files'][0];

        $this->assertSame([
            ['line' => 1, 'call' => 'read_excel', 'target' => '\\\\share\\in\\export.xlsx'],
            ['line' => 2, 'call' => 'dbGetQuery', 'target' => 'SELECT id, name FROM customers'],
        ], $file['reads']);
        $this->assertSame([
            ['line' => 4, 'call' => 'write_xlsx', 'target' => 'report.xlsx'],
            ['line' => 5, 'call' => 'saveRDS', 'target' => 'raw.rds'],
        ], $file['writes']);
        $this->assertSame([['line' => 3, 'kind' => 'stop', 'text' => 'The export is empty: check the month.']], $file['messages']);
    }

    public function test_sources_link_scripts_and_give_the_run_order(): void
    {
        $index = RScriptIndex::build([
            ['ref' => 'F1', 'name' => 'R/export.R', 'text' => 'write_csv(x, "out.csv")'],
            ['ref' => 'F2', 'name' => 'R/clean.R', 'text' => 'source(here::here("R", "export.R"))'],
            ['ref' => 'F3', 'name' => 'main.R', 'text' => "source('R/clean.R')\nsource(\"missing.R\")"],
            ['ref' => 'F4', 'name' => 'scratch.R', 'text' => 'x <- 1'],
        ]);

        $this->assertSame([['line' => 1, 'path' => 'R/clean.R', 'file' => 'F2'], ['line' => 2, 'path' => 'missing.R', 'file' => null]], $index['files'][2]['sources']);
        $this->assertSame('F1', $index['files'][1]['sources'][0]['file']);
        // nothing sources main.R or scratch.R, so both start a chain, in upload order
        $this->assertSame(['F3', 'F2', 'F1', 'F4'], $index['runOrder']);
    }

    public function test_the_script_opened_first_leads_the_run_order(): void
    {
        $index = RScriptIndex::build([
            ['ref' => 'F1', 'name' => 'clean.R', 'text' => 'x <- 1'],
            ['ref' => 'F2', 'name' => 'main.R', 'text' => 'source("clean.R")', 'entry' => true],
            ['ref' => 'F3', 'name' => 'other.R', 'text' => 'y <- 2'],
        ]);

        $this->assertSame(['F2', 'F1', 'F3'], $index['runOrder']);
        $this->assertTrue($index['files'][1]['entry']);
    }

    private function index(string $text): array
    {
        return RScriptIndex::build([['ref' => 'F1', 'name' => 'main.R', 'text' => $text]]);
    }
}
