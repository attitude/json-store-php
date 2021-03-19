<?php

namespace JSON\Store;

final class Assert {
    public static function hasValue($value) {
        assert(isset($value), 'Must have value');
        return $value;
    }

    public static function string(string $value): string {
        return $value;
    }

    public static function stringMatches(string $value, string $regex): string {
        assert(preg_match($regex, $value), 'Must match (regular) expression `{$regex}`');

        return $value;
    }

    public static function alpha(string $value): string {
        assert(preg_match('/^\w+$/', $value), 'Must be alphanumeric');

        return $value;
    }

    public static function alphanumeric(string $value): string {
        assert(preg_match('/^[\w\d]+$/', $value), 'Must be alphanumeric');

        return $value;
    }

    public static function unquotedIdentifier(string $value) {
        assert(preg_match('/^[\w\d_$]+$/', $value), 'Must be a valid unqoted indetifier string');

        return $value;
    }

    public static function quotedIdentifier(string $value): string {
        assert(preg_match('/^[^\s]+$/', $value), 'Must be quoted identifier string');

        return $value;
    }

    public static function path(string $value): string {
        assert(preg_match('/^(?:\w|\w\w|\w[\w\.]+\w)$/', $value), 'Must be an object path string');

        return $value;
    }

    public static function orderBy(string $value): string {
        if (str_starts_with(strtoupper($value), 'FIELD(')) {
            if (!preg_match('/^FIELD\(.+?\)$/i', trim($value))) {
                throw new \Exception("Bad syntax for `ORDER BY` clause; use 'ORDER BY `field1` [ASC|DESC], `field2` [ASC|DESC],...'", 400);
            }

            return $value;
        }

        $parts = array_map('trim', explode(',', $value));

        try {
            foreach ($parts as $part) {
                list ($identifier, $direction) = explode(' ', str_replace("\t", ' ', $part), 2);

                $identifier = trim($identifier);

                if ($identifier[0] === '`') {
                    static::quotedIdentifier(substr($identifier, 1, -1));
                } else {
                    static::unquotedIdentifier($identifier);
                }

                $direction = trim($direction);

                if (!preg_match('/(?:ASC|DESC)$/i', $direction)) {
                    throw new \Exception("Missing ASC or DESC in `ORDER BY` clause", 400);
                }
            }
        } catch (\Throwable $e) {
            throw new \Exception("Bad syntax for `ORDER BY` clause; use 'ORDER BY `field1` [ASC|DESC], `field2` [ASC|DESC],...'", 400, $e);
        }

        return $value;
    }
}
