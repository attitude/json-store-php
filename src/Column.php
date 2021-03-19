<?php

namespace JSON\Store;

final class Column implements \JsonSerializable {
    /**
     * A bit-value type.
     */
    const BIT = 'BIT';
    /**
     * A very small integer.
     * The signed range is -128 to 127.
     * The unsigned range is 0 to 255.
     */
    const TINYINT = 'TINYINT';
    /**
     * A small integer.
     * The signed range is -32768 to 32767.
     * The unsigned range is 0 to 65535.
     */
    const SMALLINT = 'SMALLINT';
    /**
     * A medium-sized integer.
     * The signed range is -8388608 to 8388607.
     * The unsigned range is 0 to 16777215.
     */
    const MEDIUMINT = 'MEDIUMINT';
    /**
     * A normal-size integer.
     * The signed range is -2147483648 to 2147483647.
     * The unsigned range is 0 to 4294967295.
     */
    const INT = Column::UNSIGNED_INT;
    /**
     * This type is a synonym for INT.
     */
    const INTEGER = 'INTEGER';
    /**
     * A large integer.
     * The signed range is -9223372036854775808 to 9223372036854775807.
     * The unsigned range is 0 to 18446744073709551615.
     */
    const BIGINT = 'BIGINT';

    /**
     * A very small integer.
     * The unsigned range is 0 to 255.
     */
    const UNSIGNED_TINYINT = 'TINYINT UNSIGNED';
    /**
     * A small integer.
     * The unsigned range is 0 to 65535.
     */
    const UNSIGNED_SMALLINT = 'SMALLINT UNSIGNED';
    /**
     * A medium-sized integer.
     * The unsigned range is 0 to 16777215.
     */
    const UNSIGNED_MEDIUMINT = 'MEDIUMINT UNSIGNED';
    /**
     * A normal-size integer.
     * The unsigned range is 0 to 4294967295.
     */
    const UNSIGNED_INT = 'INT UNSIGNED';
    /**
     * This type is a synonym for INT.
     */
    const UNSIGNED_INTEGER = 'INTEGER UNSIGNED';
    /**
     * A large integer.
     * The unsigned range is 0 to 18446744073709551615.
     */
    const UNSIGNED_BIGINT = 'BIGINT UNSIGNED';

    /**
     * These types are synonyms for TINYINT(1). A value of zero is considered false.
     * Nonzero values are considered true:
     */
    const BOOL = 'BOOL';
    /**
     * These types are synonyms for TINYINT(1). A value of zero is considered false.
     * Nonzero values are considered true:
     */
    const BOOLEAN = 'BOOLEAN';

    private $name;
    private $path;
    private $type;
    private $null;
    private $stored;
    private $unique;

    public function __construct(
        string $name,
        string $type,
        string $path = null,
        bool $null = false,
        bool $stored = false,
        bool $unique = false,
    ) {
        $this->name = Assert::quotedIdentifier($name);
        $this->type = self::assertType($type);
        $this->path = Assert::path($path ?? $name);
        $this->null = $null;
        $this->stored = $stored;
        $this->unique = $unique;
    }

    public static function assertType(string $type) {
        // Note: Does not check against existing types, only checks structure
        assert(preg_match('/^(?:\w+|\w+\(\d+\)|\w+[\w\s]+\w+|\w+\(\d+\)[\w\s]+\w)$/', $type), 'Column type must be on of allowed types');
        return $type;
    }

    public static function ENUM(array $enum): string {
        return printf("ENUM(%s)", implode(',', fn(int|float|string $value) => "'{$value}'", $enum));
    }

    public function __get($name) {
        return match($name) {
            'name' => $this->name,
            'type' => $this->type,
            'path' => $this->path,
            'null' => $this->null ? 'NULL' : 'NOT NULL',
            'stored' => $this->stored ? 'STORED' : 'VIRTUAL',
            'index' => $this->unique ? 'UNIQUE' : 'INDEX',
        };
    }

    public function isNull() { return $this->null; }

    public function isStored() { return $this->stored; }

    public function isVirtual() { return !$this->stored; }

    public function isUniqe() { return $this->unique; }

    public function jsonSerialize (): array {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'type' => $this->type,
            'null' => $this->null,
            'stored' => $this->stored,
            'unique' => $this->unique,
        ];
    }
}
