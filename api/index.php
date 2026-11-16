<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

// Remove server software/version disclosure headers
if (function_exists('header_remove')) {
    @header_remove('X-Powered-By');
}
@ini_set('expose_php', 'off');

// 1. Vercel Serverless Writable Environment Initialization
$tmpDirs = [
    '/tmp/views',
    '/tmp/sessions',
    '/tmp/cache',
    '/tmp/logs',
    '/tmp/storage/app/backups',
    '/tmp/storage/app/public',
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/logs',
];

foreach ($tmpDirs as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// 2. Resolve database connection mode
$useSupabasePostgres = extension_loaded('pdo_pgsql') && (getenv('DB_CONNECTION') !== 'sqlite');
$targetDb = '/tmp/database.sqlite';
$needsSeed = false;

// Prepare writable SQLite database only when SQLite fallback is active
if (! $useSupabasePostgres) {
    $sourceDb = __DIR__.'/../database/database.sqlite';

    if (! file_exists($targetDb) || filesize($targetDb) === 0) {
        if (file_exists($sourceDb) && filesize($sourceDb) > 0) {
            @copy($sourceDb, $targetDb);
        } else {
            @touch($targetDb);
            $needsSeed = true;
        }
        @chmod($targetDb, 0666);
    }
}

// 3. Set environment variables for serverless execution across putenv, $_ENV, and $_SERVER
$resolvedDbConnection = $useSupabasePostgres ? 'pgsql' : 'sqlite';
$resolvedDbHost = getenv('DB_HOST') ?: (getenv('POSTGRES_HOST') ?: '127.0.0.1');
$resolvedDbPort = getenv('DB_PORT') ?: '5432';
$resolvedDbDatabase = $useSupabasePostgres ? (getenv('DB_DATABASE') ?: (getenv('POSTGRES_DATABASE') ?: 'postgres')) : $targetDb;
$resolvedDbUsername = getenv('DB_USERNAME') ?: (getenv('POSTGRES_USER') ?: '');
$resolvedDbPassword = getenv('DB_PASSWORD') ?: (getenv('POSTGRES_PASSWORD') ?: '');

$serverlessEnv = [
    'VERCEL' => '1',
    'APP_NAME' => getenv('APP_NAME') ?: 'SPKTAD Pelindo Cirebon',
    'APP_ENV' => getenv('APP_ENV') ?: 'production',
    'APP_DEBUG' => getenv('APP_DEBUG') ?: 'false',
    'APP_TIMEZONE' => 'Asia/Jakarta',
    'APP_LOCALE' => 'id',
    'DB_CONNECTION' => $resolvedDbConnection,
    'DB_HOST' => $resolvedDbHost,
    'DB_PORT' => $resolvedDbPort,
    'DB_DATABASE' => $resolvedDbDatabase,
    'DB_USERNAME' => $resolvedDbUsername,
    'DB_PASSWORD' => $resolvedDbPassword,
    'DB_SSLMODE' => getenv('DB_SSLMODE') ?: 'require',
    'VIEW_COMPILED_PATH' => '/tmp/views',
    'SESSION_DRIVER' => getenv('SESSION_DRIVER') ?: 'cookie',
    'SESSION_ENCRYPT' => 'true',
    'CACHE_STORE' => getenv('CACHE_STORE') ?: 'array',
    'LOG_CHANNEL' => 'stderr',
    'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php',
];

foreach (['APP_KEY', 'DB_URL', 'POSTGRES_URL', 'DATABASE_URL', 'SUPABASE_PROJECT_REF', 'SUPABASE_URL'] as $passthroughVar) {
    $val = getenv($passthroughVar);
    if ($val !== false && $val !== '') {
        $serverlessEnv[$passthroughVar] = $val;
    }
}

foreach ($serverlessEnv as $key => $val) {
    putenv("{$key}={$val}");
    $_ENV[$key] = $val;
    $_SERVER[$key] = $val;
}

// 4. In-process self-healing migration and seeder fallback if database is fresh
if ($needsSeed) {
    $autoloadPath = __DIR__.'/../vendor/autoload.php';
    $bootstrapPath = __DIR__.'/../bootstrap/app.php';

    if (file_exists($autoloadPath) && file_exists($bootstrapPath)) {
        require $autoloadPath;
        $app = require_once $bootstrapPath;
        $kernel = $app->make(Kernel::class);
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\ProductionSeeder', '--force' => true]);
        $app->handleRequest(Request::capture());

        return;
    }
}

// 5. Delegate execution to Laravel public index
require __DIR__.'/../public/index.php';
