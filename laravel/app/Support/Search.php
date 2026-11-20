<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One search behaviour for every list/search endpoint in the app.
 *
 *  - Case-insensitive.
 *  - Whitespace-separated tokens; every token must hit at least one column, in any order.
 *  - Separator-insensitive: "ab-123 blk" finds "AB123BLK" (strips - _ . / and spaces).
 *  - Regex: wrap the query in slashes — /^AB-\d+/ — to use a POSIX regex (case-insensitive).
 *    On drivers without regex support (sqlite) it falls back to a literal match.
 *  - Fuzzy: on PostgreSQL with pg_trgm installed, tokens of 4+ chars also match by trigram
 *    word-similarity, so typos ("alumnium") still find results. Without the extension the
 *    fuzzy leg is silently skipped.
 *
 * Columns may be dotted ("supplier.name") to search through a relation (whereHas).
 */
class Search
{
    /** Minimum trigram word-similarity for a fuzzy hit (0..1). */
    private const FUZZY_THRESHOLD = 0.45;

    /**
     * @param  EloquentBuilder|QueryBuilder|Relation  $query
     * @param  string[]  $columns  e.g. ['sku', 'description', 'supplier.name']
     */
    public static function apply($query, ?string $term, array $columns)
    {
        $term = trim((string) $term);
        if ($term === '' || ! $columns) {
            return $query;
        }

        $regex = self::parseRegex($term);
        if ($regex !== null) {
            $query->where(fn ($q) => self::eachColumn($q, $columns, fn ($sub, $col) => self::regexClause($sub, $col, $regex)));

            return $query;
        }

        foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $query->where(fn ($q) => self::eachColumn($q, $columns, fn ($sub, $col) => self::tokenClause($sub, $col, $token)));
        }

        return $query;
    }

    /**
     * In-memory twin of apply() for collections: same case/token/separator/regex/typo rules.
     */
    public static function matches(?string $term, mixed ...$fields): bool
    {
        $term = trim((string) $term);
        if ($term === '') {
            return true;
        }
        $texts = [];
        array_walk_recursive($fields, function ($v) use (&$texts) {
            if ($v !== null && $v !== '') {
                $texts[] = (string) $v;
            }
        });
        $hay = mb_strtolower(implode(' ', $texts));

        $regex = self::parseRegex($term);
        if ($regex !== null) {
            return (bool) preg_match('/'.str_replace('/', '\\/', $regex).'/iu', implode(' ', $texts));
        }

        $hayStrip = preg_replace('/[^\p{L}\p{N}]/u', '', $hay);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $hay, -1, PREG_SPLIT_NO_EMPTY);
        foreach (preg_split('/\s+/', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (str_contains($hay, $token)) {
                continue;
            }
            $ts = preg_replace('/[^\p{L}\p{N}]/u', '', $token);
            if ($ts !== '' && str_contains($hayStrip, $ts)) {
                continue;
            }
            $max = mb_strlen($ts) >= 8 ? 2 : 1;
            $hit = false;
            if (mb_strlen($ts) >= 4) {
                foreach ($words as $w) {
                    if (levenshtein($ts, $w) <= $max || (strlen($w) > strlen($ts) && levenshtein($ts, substr($w, 0, strlen($ts))) <= $max)) {
                        $hit = true;
                        break;
                    }
                }
            }
            if (! $hit) {
                return false;
            }
        }

        return true;
    }

    /** Escape LIKE wildcards in user input. */
    public static function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    /** "/pattern/" → "pattern" (null if not a usable regex). */
    private static function parseRegex(string $term): ?string
    {
        if (strlen($term) >= 3 && $term[0] === '/' && substr($term, -1) === '/') {
            $pattern = substr($term, 1, -1);
            if (@preg_match('/'.str_replace('/', '\/', $pattern).'/', '') !== false) {
                return $pattern;
            }
        }

        return null;
    }

    /** Group columns with OR, routing dotted ones through whereHas on their relation. */
    private static function eachColumn($q, array $columns, \Closure $apply): void
    {
        $byRelation = [];
        foreach ($columns as $col) {
            if (str_contains($col, '.') && self::isRelationPath($q, $col)) {
                $pos = strrpos($col, '.');
                $byRelation[substr($col, 0, $pos)][] = substr($col, $pos + 1);
            } else {
                $q->orWhere(fn ($sub) => $apply($sub, $col));
            }
        }
        foreach ($byRelation as $relation => $cols) {
            $q->orWhereHas($relation, function ($rq) use ($cols, $apply) {
                $rq->where(function ($inner) use ($cols, $apply) {
                    foreach ($cols as $c) {
                        $inner->orWhere(fn ($sub) => $apply($sub, $c));
                    }
                });
            });
        }
    }

    /** "table.column" (qualified) vs "relation.column": only Eloquent builders can do relations. */
    private static function isRelationPath($q, string $col): bool
    {
        if (! $q instanceof EloquentBuilder) {
            return false;
        }
        $model = $q->getModel();
        $relation = explode('.', $col)[0];

        return method_exists($model, $relation) && $relation !== $model->getTable();
    }

    private static function tokenClause($q, string $col, string $token): void
    {
        $grammar = $q->getGrammar();
        $wrapped = $grammar->wrap($col);
        $lower = mb_strtolower($token);

        // 1) plain case-insensitive substring
        $q->orWhereRaw("LOWER(CAST($wrapped AS TEXT)) LIKE ? ESCAPE '\\'", ['%'.self::escapeLike($lower).'%']);

        // 2) separator-insensitive substring
        $stripped = preg_replace('/[^\p{L}\p{N}]/u', '', $lower);
        if ($stripped !== '') {
            $q->orWhereRaw(self::strip("CAST($wrapped AS TEXT)")." LIKE ? ESCAPE '\\'", ['%'.self::escapeLike($stripped).'%']);
        }

        // 3) fuzzy (pg_trgm word similarity) for typos
        if (mb_strlen($stripped) >= 4 && self::hasTrigram()) {
            $q->orWhereRaw("word_similarity(?, LOWER(CAST($wrapped AS TEXT))) >= ?", [$lower, self::FUZZY_THRESHOLD]);
        }
    }

    private static function regexClause($q, string $col, string $pattern): void
    {
        $wrapped = $q->getGrammar()->wrap($col);
        $driver = DB::connection($q->getConnection()->getName())->getDriverName();

        if ($driver === 'pgsql') {
            $q->orWhereRaw("CAST($wrapped AS TEXT) ~* ?", [$pattern]);
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $q->orWhereRaw("CAST($wrapped AS CHAR) REGEXP ?", [$pattern]);
        } else {
            $q->orWhereRaw("LOWER(CAST($wrapped AS TEXT)) LIKE ? ESCAPE '\\'", ['%'.self::escapeLike(mb_strtolower($pattern)).'%']);
        }
    }

    /** SQL expression: lower-cased value with common separators removed. */
    private static function strip(string $expr): string
    {
        $out = "LOWER($expr)";
        foreach (['-', ' ', '.', '/', '_', ',', '(', ')', '#'] as $ch) {
            $out = "REPLACE($out, '$ch', '')";
        }

        return $out;
    }

    private static function hasTrigram(): bool
    {
        return Cache::store('array')->rememberForever('search.pg_trgm.'.DB::getDefaultConnection(), function () {
            try {
                if (DB::connection()->getDriverName() !== 'pgsql') {
                    return false;
                }

                return (bool) DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'pg_trgm'");
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
