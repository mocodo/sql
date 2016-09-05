# mocodo/sql

Provides a small extension to the native PDO behavior, allowing simple query build.

## Installation

```bash
$ composer require mocodo/sql
```

## Tests

```bash
$ composer tests
```

## Usage

As it is simply extending PDO, you can instanciate it like so :

```php
<?php

use Mocodo\Driver\MySQLConnection

$pdo = new MySQLConnection('mysql:127.0.0.1;dbname=test', 'root', 'root', [...]);
```

It also comes with a neat feature for building queries.

```php
$query = 'SELECT foo, bar FROM my_table WHERE 1';

$stmt = $pdo->find($query, [
    'conditions' => [
        'foo' => 'foobar',
        'bar LIKE' => '%.com',
    ]
]);

// SELECT foo, bar FROM my_table WHERE 1 AND foo = 'foobar' AND bar LIKE '%.com'
```

## License

MIT
