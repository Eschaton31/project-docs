<?php

namespace App\Ai\Scaffold;

/**
 * Blanks out obvious secrets in R code before it is stored or sent to a model: string values given to names
 * that end in password, secret, token or api key, passwords in URLs and in connection strings. Lookups such as
 * Sys.getenv("DB_PASSWORD") stay, since the docs should say the variable is needed.
 */
final class SecretRedactor
{
    public const MASK = '<redacted>';

    private const PATTERNS = [
        // db_password <- "x", pwd = 'x' (also as a named argument), never == comparisons
        'assignment' => '/(\b[\w.]*(?:password|passwd|pwd|secret|token|api_?key)\b\s*(?:<<-|<-|=(?!=))\s*)(["\'])((?:(?!\2).)+)\2/i',
        // scheme://user:password@host
        'url' => '#(\b[a-z][a-z0-9+.-]*://[^:/\s"\'@]+:)([^@\s"\']+)(@)#i',
        // ...;PWD=x; inside an ODBC/JDBC connection string
        'connection_string' => '/(\b(?:pwd|password)=)([^;"\'\s]+)(?=;|["\'])/i',
    ];

    /**
     * @return array{text: string, redactions: list<array{line: int, kind: string}>}
     */
    public static function redact(string $code): array
    {
        $redactions = [];
        $lines = explode("\n", $code);

        foreach ($lines as $i => $line) {
            foreach (self::PATTERNS as $kind => $pattern) {
                $line = preg_replace_callback($pattern, function (array $match) use ($kind, $i, &$redactions) {
                    $redactions[] = ['line' => $i + 1, 'kind' => $kind];

                    return match ($kind) {
                        'assignment' => $match[1].$match[2].self::MASK.$match[2],
                        'url' => $match[1].self::MASK.$match[3],
                        'connection_string' => $match[1].self::MASK,
                    };
                }, $line);
            }
            $lines[$i] = $line;
        }

        return ['text' => implode("\n", $lines), 'redactions' => $redactions];
    }
}
