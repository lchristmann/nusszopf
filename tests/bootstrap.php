<?php

require __DIR__.'/../vendor/autoload.php';

/*
 * The suite must never run against the development database, Redis queue or
 * search index. phpunit.xml forces its <env> values into $_ENV and putenv(),
 * but Laravel's Env reads $_SERVER first, and inside the Compose containers
 * $_SERVER carries the development values from `.env` — which then silently
 * won (the suite migrated the development database afresh and flooded its
 * queue worker). Copy every forced value over.
 */
foreach ($_ENV as $name => $value) {
    if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== $value) {
        $_SERVER[$name] = $value;
    }
}
