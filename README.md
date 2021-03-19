# JSON Store for PHP
>JSON store built on top of MySQL 8 JSON type and PHP 8

**Warning: Highly experimental project**

With MySQL 5.7 and JSON comumn type it became possible to use MySQL as NoSQL.

By adding virtual and stored columns derived from the JSON column we're getting
ability to query those object in performant way.

**This allows you to**
- store JSON in MySQL
- be able to use simple get/set/delete API
- query data as with regular tables

*Tested with MySQL 8.0 and PHP 8.*

---

## Identificators

Uses simplified Universal Time Identificators instead of UUIDs — UTID. UTID are
transfmited and stored as 9 characters ID (72 bits) representing timestamps in nanoseconds.

UTIDs are encoded in 64 base string using only `-`, `A-Z`, `a-z` and `_` in this order
making UTIDs sortable in time because comversion of numbers to chars maintains
its sorting capabilities.

## Instal with Composer

Edit `composer.json`:

```json
{
    ...,
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/attitude/json-store-php"
        }
    ],
    "require": {
        "attitude/json-store-php": "dev-main"
    }
}
```

## Usage

**Important:** Always use `;charset=utf8mb4` when connecting to database.

```php
<?php

require_once 'vendor/autoload.php';

use JSON\Store\Space;
use JSON\Store\Column;
use JSON\Store\Measure;
use JSON\Store\Store;

try {
    $start = Measure::start();

    $connection = new PDO("mysql:host=localhost;port=3314;dbname=test;charset=utf8mb4", "user", "password");

    Space::register(
        space: 'events',
        connection: $connection,
        table: 'test_events',
        columns: [
            new Column(
                name: 'slug',
                type: 'VARCHAR(64)',
                unique: true,
            ),
            new Column(
                name: 'title',
                type: 'VARCHAR(256)',
                null: true,
            ),
        ],
    );

    // Space::setup(space: 'events'); // Run this only to sync space config

    $slug = 'some-title';

    $id = Store::set('events', [
        'slug' => $slug,
        'some' => 'data5',
        'to' => 'insert5',
    ]);

    var_dump(Store::exists('events', '4jT09VzfN'));
    var_dump(Store::exists('events', $id));
    var_dump(Store::existsBy('events', 'slug', $slug));
    var_dump(Store::get('events', $id));
    var_dump(Store::getBy('events', 'slug', $slug));

    Store::delete('events', $id);

    $performance = Measure::stop($start);

    echo "Performance: {$performance}s\n";
} catch (\Throwable $e) {
    print_r($e);
}

```

Resuts:

```
bool(false)
bool(true)
bool(true)
array(4) {
  ["id"]=>
  string(9) "4jTC-LC3W"
  ["to"]=>
  string(7) "insert5"
  ["slug"]=>
  string(10) "some-title"
  ["some"]=>
  string(5) "data5"
}
array(4) {
  ["id"]=>
  string(9) "4jTC-LC3W"
  ["to"]=>
  string(7) "insert5"
  ["slug"]=>
  string(10) "some-title"
  ["some"]=>
  string(5) "data5"
}
Performance: 0.257438s
```

---

*Enjoy!*

---

Created by [martin_adamko](https://twitter.com/martin_adamko)

---
## TODOs:

- [ ] Implement find methods
- [ ] Implement cursor offsets
- [ ] Allow other UUID implementations
