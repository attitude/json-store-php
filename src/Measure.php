<?php

namespace JSON\Store;

use UniversalTime\Identificator\UTID;

class Measure {
    private static $measurements = [];
    private static $active = true;

    public static function active(bool $active) {
        static::$active = $active;
    }

    public static function start(string $key = null) {
        if (!static::$active) { return; }

        $now = UTID::nanoseconds();
        $key = $key ?? UTID::encode($now);

        static::$measurements[] = [$key, $now];

        return $key;
    }

    private static function indexOf(string $key): false|int|string {
        return array_search($key, array_column(static::$measurements, 0));
    }

    private static function find(string $key) {
        $index = static::indexOf($key);

        if ($index !== false) {
            return static::$measurements[$index];
        }

        throw new \Exception('Not found', 404);
    }

    public static function since(string $key) {
        if (!static::$active) { return; }

        $measurement = self::find($key);
        $now = UTID::nanoseconds();

        return ($now - $measurement[1]) / UTID::PRECISION;
    }

    public static function stop(string $key) {
        if (!static::$active) { return; }

        $index = static::indexOf($key);
        $delta = (UTID::nanoseconds() - static::$measurements[$index][1]) / UTID::PRECISION;
        static::$measurements = array_slice(static::$measurements, 0, $index);

        return $delta;
    }
}
