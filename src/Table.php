<?php

namespace JSON\Store;

final class Table {
    private CONST NO_ERROR = '00000';

    private function __construct() {}

    public static function create(\PDO $connection, string $name, string $primaryKey, string $primaryKeyType, string $docKey) {
        $query = "CREATE TABLE `{$name}` (
            `{$primaryKey}` {$primaryKeyType} AS (`{$docKey}` ->> '\$.{$primaryKey}') STORED NOT NULL,
            `{$docKey}` JSON NOT NULL,
            PRIMARY KEY (`{$primaryKey}`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $connection->query($query);
    }

    public static function exists(\PDO $connection, string $name): bool {
        try {
            $statement = $connection->prepare("SELECT 1 FROM `{$name}` LIMIT 1");
            $statement->execute();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function columns(\PDO $connection, string $name): array {
        $statement = $connection->query("SHOW COLUMNS FROM {$name}");

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function indexes(\PDO $connection, string $name): array {
        $statement = $connection->query("SHOW INDEXES FROM {$name}");

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function addColumn(\PDO $connection, string $name, Column $column, string $docKey) {
        if (strtoupper($column->type) === 'BOOL' || strtoupper($column->type) === 'BOOLEAN') {
            $statement = $connection->query(
                "ALTER TABLE `{$name}`
                ADD `{$column->name}` {$column->type} AS (`{$docKey}` -> '$.{$column->path}' = TRUE)
                {$column->stored} {$column->null} AFTER `{$docKey}`, ADD {$column->index} (`{$column->name}`);"
            );
        } else {
            $statement = $connection->query(
                "ALTER TABLE `{$name}`
                ADD `{$column->name}` {$column->type} AS (`{$docKey}` ->> '$.{$column->path}')
                {$column->stored} {$column->null} AFTER `{$docKey}`, ADD {$column->index} (`{$column->name}`);"
            );
        }

        if ($statement->errorCode() !== self::NO_ERROR) {
            throw new \Exception("Failed to add column {$name}", 500);
        }
    }

    public static function dropColumn(\PDO $connection, string $name, Column $column) {
        $statement = $connection->query(
            "ALTER TABLE `{$name}`
            DROP `{$column->name}`;"
        );

        if ($statement->errorCode() !== self::NO_ERROR) {
            throw new \Exception("Failed to drop column {$name}", 500);
        }
    }
}
