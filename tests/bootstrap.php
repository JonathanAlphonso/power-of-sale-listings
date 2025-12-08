<?php

/**
 * PHPUnit Bootstrap - SAFETY GUARD
 *
 * This bootstrap ensures tests ALWAYS use SQLite in-memory database
 * to prevent accidental data loss in production databases.
 */

// Force SQLite in-memory BEFORE Laravel loads
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';
