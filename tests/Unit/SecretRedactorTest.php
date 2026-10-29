<?php

namespace Tests\Unit;

use App\Ai\Scaffold\SecretRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecretRedactorTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function secrets(): array
    {
        return [
            'assigned password' => ['db_password <- "hunter2"', 'db_password <- "<redacted>"', 'assignment'],
            'single quotes and =' => ["api_key = 'abc123'", "api_key = '<redacted>'", 'assignment'],
            'global assignment' => ['TOKEN <<- "t0k"', 'TOKEN <<- "<redacted>"', 'assignment'],
            'named argument' => ['con <- dbConnect(odbc(), uid = "me", pwd = "s3cret")', 'con <- dbConnect(odbc(), uid = "me", pwd = "<redacted>")', 'assignment'],
            'Sys.setenv' => ['Sys.setenv(CLIENT_SECRET = "xyz")', 'Sys.setenv(CLIENT_SECRET = "<redacted>")', 'assignment'],
            'user and password in a URL' => ['url <- "postgres://etl:p%40ss@db.local/prod"', 'url <- "postgres://etl:<redacted>@db.local/prod"', 'url'],
            'ODBC connection string' => ['cs <- "Driver={SQL Server};Server=s1;UID=me;PWD=abc;"', 'cs <- "Driver={SQL Server};Server=s1;UID=me;PWD=<redacted>;"', 'connection_string'],
        ];
    }

    #[DataProvider('secrets')]
    public function test_secrets_are_redacted(string $line, string $expected, string $kind): void
    {
        $result = SecretRedactor::redact("x <- 1\n{$line}\n");

        $this->assertSame("x <- 1\n{$expected}\n", $result['text']);
        $this->assertSame([['line' => 2, 'kind' => $kind]], $result['redactions']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nearMisses(): array
    {
        return [
            'a name that only starts with a keyword' => ['password_prompt <- "Enter password"'],
            'a lookup, which the docs should mention' => ['pwd <- Sys.getenv("DB_PASSWORD")'],
            'a variable passed on' => ['dbConnect(odbc(), password = pw)'],
            'a comparison' => ['if (token == "none") stop("No token")'],
            'an empty value' => ['password <- ""'],
            'a URL without a password' => ['url <- "https://example.com/data.csv"'],
        ];
    }

    #[DataProvider('nearMisses')]
    public function test_lookalikes_are_kept(string $line): void
    {
        $result = SecretRedactor::redact($line);

        $this->assertSame($line, $result['text']);
        $this->assertSame([], $result['redactions']);
    }
}
