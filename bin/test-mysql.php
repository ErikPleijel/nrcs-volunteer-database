<?php

/**
 * Portable wrapper for `composer test`: sets real process-level env vars
 * (not .env.testing — phpunit.xml already force-sets DB_CONNECTION/DB_DATABASE
 * via <env>, and Laravel's Dotenv is immutable, so a .env file can't win)
 * before invoking Pest, so it runs against the MySQL testing database
 * instead of the SQLite default. Avoids inline `VAR=value` shell syntax,
 * which cmd.exe (Windows) doesn't support.
 */

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=redcross_volunteers_testing');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

passthru('vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest', $code);

exit($code);
