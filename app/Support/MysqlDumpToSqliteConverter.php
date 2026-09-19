<?php

namespace App\Support;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Convert a phpMyAdmin / mysqldump SQL file into a SQLite database.
 *
 * Handles CREATE TABLE + INSERT + ALTER (PRIMARY/KEY/UNIQUE).
 * Skips ENGINE/CHARSET, AUTO_INCREMENT MODIFY, and FOREIGN KEY constraints.
 */
class MysqlDumpToSqliteConverter
{
    /**
     * @return array{
     *   tables: list<string>,
     *   statements_ok: int,
     *   statements_skipped: int,
     *   statements_failed: list<array{sql:string,error:string}>,
     *   row_counts: array<string,int>
     * }
     */
    public function import(string $dumpPath, string $sqlitePath, bool $overwrite = true): array
    {
        if (! is_file($dumpPath) || ! is_readable($dumpPath)) {
            throw new InvalidArgumentException("Dump not readable: {$dumpPath}");
        }

        $dir = dirname($sqlitePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (is_file($sqlitePath)) {
            if (! $overwrite) {
                throw new RuntimeException("SQLite file already exists: {$sqlitePath}");
            }
            @unlink($sqlitePath);
        }

        $pdo = new PDO('sqlite:'.$sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        $sql = file_get_contents($dumpPath);
        if ($sql === false) {
            throw new RuntimeException("Failed to read dump: {$dumpPath}");
        }

        // Normalize line endings and strip BOM.
        $sql = preg_replace("/^\xEF\xBB\xBF/", '', $sql) ?? $sql;
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);

        $statements = $this->splitStatements($sql);
        $tables = [];
        $ok = 0;
        $skipped = 0;
        $failed = [];

        $pdo->beginTransaction();
        try {
            foreach ($statements as $raw) {
                $stmt = trim($raw);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }

                // Drop MySQL conditional comments /*!...*/ keeping inner SQL when useful.
                $stmt = preg_replace('/\/\*!\d{5}\s+(.*?)\*\//s', '$1', $stmt) ?? $stmt;
                $stmt = preg_replace('/\/\*.*?\*\//s', '', $stmt) ?? $stmt;
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }

                $upper = strtoupper($stmt);

                if (str_starts_with($upper, 'SET ')
                    || str_starts_with($upper, 'START TRANSACTION')
                    || str_starts_with($upper, 'COMMIT')
                    || str_starts_with($upper, 'ROLLBACK')
                    || str_starts_with($upper, 'LOCK TABLES')
                    || str_starts_with($upper, 'UNLOCK TABLES')
                    || str_starts_with($upper, 'USE ')
                ) {
                    $skipped++;
                    continue;
                }

                // Skip FK constraints entirely.
                if (preg_match('/\bADD\s+CONSTRAINT\b/i', $stmt)
                    || preg_match('/\bFOREIGN\s+KEY\b/i', $stmt)
                ) {
                    $skipped++;
                    continue;
                }

                // Skip AUTO_INCREMENT MODIFY.
                if (preg_match('/\bMODIFY\b.*\bAUTO_INCREMENT\b/i', $stmt)) {
                    $skipped++;
                    continue;
                }

                try {
                    if (preg_match('/^CREATE\s+TABLE\b/i', $stmt)) {
                        $converted = $this->convertCreateTable($stmt);
                        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["`]?(\w+)["`]?/i', $converted, $m)) {
                            $tables[] = $m[1];
                        }
                        $pdo->exec($converted);
                        $ok++;
                        continue;
                    }

                    if (preg_match('/^INSERT\s+INTO\b/i', $stmt)) {
                        $converted = $this->convertInsert($stmt);
                        $pdo->exec($converted);
                        $ok++;
                        continue;
                    }

                    if (preg_match('/^ALTER\s+TABLE\b/i', $stmt)) {
                        foreach ($this->convertAlterTable($stmt) as $alterSql) {
                            $pdo->exec($alterSql);
                            $ok++;
                        }
                        continue;
                    }

                    // DROP TABLE etc. — convert backticks only.
                    $pdo->exec($this->quoteIdentifiers($stmt));
                    $ok++;
                } catch (\Throwable $e) {
                    $failed[] = [
                        'sql' => mb_substr($stmt, 0, 240),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $rowCounts = [];
        foreach (array_unique($tables) as $table) {
            try {
                $rowCounts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();
            } catch (\Throwable $e) {
                $rowCounts[$table] = -1;
            }
        }

        return [
            'tables' => array_values(array_unique($tables)),
            'statements_ok' => $ok,
            'statements_skipped' => $skipped,
            'statements_failed' => $failed,
            'row_counts' => $rowCounts,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $inString = false;
        $stringChar = '';
        $escaped = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            // Line comments --
            if (! $inString && $ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($inString) {
                $buf .= $ch;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($ch === $stringChar) {
                    // MySQL '' escape inside single quotes
                    if ($stringChar === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= $sql[++$i];
                        continue;
                    }
                    $inString = false;
                    $stringChar = '';
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';') {
                $out[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    private function convertCreateTable(string $stmt): string
    {
        // Extract table name and body.
        if (! preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.*)\)\s*(?:ENGINE|DEFAULT|CHARSET|COLLATE|;|$)/is', $stmt, $m)) {
            // Fallback: strip engine clause and backticks.
            $stmt = preg_replace('/\s*ENGINE\s*=\s*\w+/i', '', $stmt) ?? $stmt;
            $stmt = preg_replace('/\s*DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $stmt) ?? $stmt;
            $stmt = preg_replace('/\s*COLLATE\s*=\s*\w+/i', '', $stmt) ?? $stmt;
            $stmt = preg_replace('/\s*AUTO_INCREMENT\s*=\s*\d+/i', '', $stmt) ?? $stmt;

            return $this->quoteIdentifiers($stmt);
        }

        $table = $m[1];
        $body = $m[2];

        // Remove inline KEY / UNIQUE / PRIMARY / CONSTRAINT / FULLTEXT definitions from CREATE body;
        // indexes come from later ALTER TABLE in dumps.
        $lines = $this->splitCreateBodyLines($body);
        $cols = [];
        $hasIdPk = false;

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (preg_match('/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|FULLTEXT|CONSTRAINT|UNIQUE\s+INDEX)\b/i', $trim)) {
                continue;
            }

            // Column definition
            if (preg_match('/^`?(\w+)`?\s+(.+)$/s', $trim, $cm)) {
                $col = $cm[1];
                $def = $cm[2];
                $def = $this->convertColumnType($def);

                if (strcasecmp($col, 'id') === 0 && ! $hasIdPk) {
                    // Prefer INTEGER PRIMARY KEY for sqlite rowid affinity.
                    if (! preg_match('/\bPRIMARY\s+KEY\b/i', $def)) {
                        $def = preg_replace('/\bNOT\s+NULL\b/i', '', $def) ?? $def;
                        $def = trim($def).' PRIMARY KEY';
                    }
                    $hasIdPk = true;
                }

                $cols[] = '"'.$col.'" '.$def;
            }
        }

        return 'CREATE TABLE IF NOT EXISTS "'.$table.'" ('.implode(', ', $cols).')';
    }

    /**
     * @return list<string>
     */
    private function splitCreateBodyLines(string $body): array
    {
        $lines = [];
        $buf = '';
        $len = strlen($body);
        $depth = 0;
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($inString) {
                $buf .= $ch;
                if ($ch === $stringChar && ($i === 0 || $body[$i - 1] !== '\\')) {
                    if ($stringChar === "'" && $i + 1 < $len && $body[$i + 1] === "'") {
                        $buf .= $body[++$i];
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
                $buf .= $ch;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $buf .= $ch;
                continue;
            }
            if ($ch === ',' && $depth === 0) {
                $lines[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $lines[] = $buf;
        }

        return $lines;
    }

    private function convertColumnType(string $def): string
    {
        $def = preg_replace('/\bCURRENT_TIMESTAMP\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $def) ?? $def;
        $def = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i', '', $def) ?? $def;
        $def = preg_replace('/\bAUTO_INCREMENT\b/i', '', $def) ?? $def;
        $def = preg_replace('/\bUNSIGNED\b/i', '', $def) ?? $def;
        $def = preg_replace('/\bZEROFILL\b/i', '', $def) ?? $def;
        $def = preg_replace('/\bCHARACTER\s+SET\s+\w+/i', '', $def) ?? $def;
        $def = preg_replace('/\bCOLLATE\s+\w+/i', '', $def) ?? $def;

        // Map MySQL types → SQLite affinities.
        $def = preg_replace('/\bTINYINT(?:\(\d+\))?(?:\s+UNSIGNED)?\b/i', 'INTEGER', $def) ?? $def;
        $def = preg_replace('/\bSMALLINT(?:\(\d+\))?\b/i', 'INTEGER', $def) ?? $def;
        $def = preg_replace('/\bMEDIUMINT(?:\(\d+\))?\b/i', 'INTEGER', $def) ?? $def;
        $def = preg_replace('/\bBIGINT(?:\(\d+\))?\b/i', 'INTEGER', $def) ?? $def;
        $def = preg_replace('/\bINT(?:\(\d+\))?\b/i', 'INTEGER', $def) ?? $def;
        $def = preg_replace('/\bDOUBLE(?:\(\d+\s*,\s*\d+\))?\b/i', 'REAL', $def) ?? $def;
        $def = preg_replace('/\bFLOAT(?:\(\d+\s*,\s*\d+\))?\b/i', 'REAL', $def) ?? $def;
        $def = preg_replace('/\bDECIMAL\(\d+\s*,\s*\d+\)\b/i', 'REAL', $def) ?? $def;
        $def = preg_replace('/\bNUMERIC\(\d+\s*,\s*\d+\)\b/i', 'REAL', $def) ?? $def;
        $def = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $def) ?? $def;
        $def = preg_replace('/\bMEDIUMTEXT\b/i', 'TEXT', $def) ?? $def;
        $def = preg_replace('/\bTINYTEXT\b/i', 'TEXT', $def) ?? $def;
        $def = preg_replace('/\bLONGVARBINARY\b/i', 'BLOB', $def) ?? $def;
        $def = preg_replace('/\bVARBINARY\(\d+\)\b/i', 'BLOB', $def) ?? $def;
        $def = preg_replace('/\bBLOB\b/i', 'BLOB', $def) ?? $def;
        $def = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $def) ?? $def;
        $def = preg_replace('/\bSET\s*\([^)]*\)/i', 'TEXT', $def) ?? $def;
        // varchar/char/text/datetime/timestamp/date/time kept as TEXT affinity via names SQLite accepts.
        $def = preg_replace('/\bVARCHAR\(\d+\)/i', 'TEXT', $def) ?? $def;
        $def = preg_replace('/\bCHAR\(\d+\)/i', 'TEXT', $def) ?? $def;

        return trim(preg_replace('/\s+/', ' ', $def) ?? $def);
    }

    private function convertInsert(string $stmt): string
    {
        // Convert backticks on table/columns; keep string literals.
        $stmt = $this->quoteIdentifiers($stmt);
        // MySQL escaped quotes \' → SQLite ''
        // Careful: only outside already-normalized strings — dump uses \'
        $stmt = str_replace("\\'", "''", $stmt);
        // Hex / binary rarely present; leave as-is.
        return $stmt;
    }

    /**
     * @return list<string>
     */
    private function convertAlterTable(string $stmt): array
    {
        if (! preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+(.*)$/is', $stmt, $m)) {
            return [];
        }

        $table = $m[1];
        $rest = trim($m[2]);
        $out = [];

        // Split on commas at top level (indexes list).
        $parts = $this->splitCreateBodyLines($rest);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // PRIMARY KEY — skip if already set on CREATE; try CREATE UNIQUE INDEX fallback only if needed.
            if (preg_match('/^ADD\s+PRIMARY\s+KEY\s*\((.+)\)/i', $part, $pm)) {
                // Already PRIMARY KEY on id in CREATE; skip silently.
                continue;
            }

            if (preg_match('/^ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?(\w+)`?\s*\((.+)\)/i', $part, $im)) {
                $indexName = $im[1];
                $cols = $im[2];
                $unique = (bool) preg_match('/^ADD\s+UNIQUE\b/i', $part);
                $cols = preg_replace('/`/', '"', $cols) ?? $cols;
                // Skip unique on vin — dump may conflict with soft-deleted duplicates; Laravel adds partial unique later.
                if ($unique && strcasecmp($table, 'car') === 0 && stripos($cols, 'vin') !== false) {
                    continue;
                }
                $kw = $unique ? 'CREATE UNIQUE INDEX' : 'CREATE INDEX';
                $out[] = sprintf('%s IF NOT EXISTS "%s" ON "%s" (%s)', $kw, $table.'_'.$indexName, $table, $cols);
                continue;
            }
        }

        return $out;
    }

    private function quoteIdentifiers(string $sql): string
    {
        return preg_replace('/`([^`]+)`/', '"$1"', $sql) ?? $sql;
    }
}
