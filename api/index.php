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

// 2. Prepare writable SQLite database as guaranteed baseline fallback
$sourceDb = __DIR__.'/../database/database.sqlite';
$targetDb = '/tmp/database.sqlite';
$needsSeed = false;

if (! file_exists($targetDb) || filesize($targetDb) === 0) {
    if (file_exists($sourceDb) && filesize($sourceDb) > 0) {
        @copy($sourceDb, $targetDb);
    } else {
        @touch($targetDb);
        $needsSeed = true;
    }
    @chmod($targetDb, 0666);
}

// 3. Resolve PostgreSQL (Supabase Singapore Pooler) credentials
$defaultSupabaseHost = 'aws-1-ap-southeast-1.pooler.supabase.com';
$defaultSupabaseUser = 'postgres.fnceytkzkowaomjknqet';
$defaultSupabasePass = 'klyh6qYrEgsqZbMu';
$defaultSupabaseDb = 'postgres';
$defaultSupabasePort = '5432';

$resolvedDbHost = getenv('DB_HOST') ?: (getenv('POSTGRES_HOST') ?: $defaultSupabaseHost);
$resolvedDbPort = getenv('DB_PORT') ?: $defaultSupabasePort;
$resolvedDbDatabase = getenv('DB_DATABASE') ?: (getenv('POSTGRES_DATABASE') ?: $defaultSupabaseDb);
$resolvedDbUsername = getenv('DB_USERNAME') ?: (getenv('POSTGRES_USER') ?: $defaultSupabaseUser);
$resolvedDbPassword = getenv('DB_PASSWORD') ?: (getenv('POSTGRES_PASSWORD') ?: $defaultSupabasePass);

// 4. Test PostgreSQL connection availability with quick timeout ping
$useSupabasePostgres = false;
if (extension_loaded('pdo_pgsql') && getenv('DB_CONNECTION') !== 'sqlite') {
    try {
        $testDsn = "pgsql:host={$resolvedDbHost};port={$resolvedDbPort};dbname={$resolvedDbDatabase};sslmode=require";
        $testPdo = new PDO($testDsn, $resolvedDbUsername, $resolvedDbPassword, [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        ]);
        $useSupabasePostgres = true;
    } catch (Throwable $e) {
        // Fall back gracefully to bundled SQLite
        $useSupabasePostgres = false;
    }
}

$resolvedDbConnection = $useSupabasePostgres ? 'pgsql' : 'sqlite';
$finalDbDatabase = $useSupabasePostgres ? $resolvedDbDatabase : $targetDb;

$serverlessEnv = [
    'VERCEL' => '1',
    'APP_NAME' => getenv('APP_NAME') ?: 'SPKTAD Pelindo Cirebon',
    'APP_ENV' => getenv('APP_ENV') ?: 'production',
    'APP_DEBUG' => getenv('APP_DEBUG') ?: 'false',
    'APP_KEY' => getenv('APP_KEY') ?: 'base64:fA3eoib3fK3pvs6TACojMNSSwg2TWvuJ892sQ3KuSCQ=',
    'APP_TIMEZONE' => 'Asia/Jakarta',
    'APP_LOCALE' => 'id',
    'DB_CONNECTION' => $resolvedDbConnection,
    'DB_HOST' => $resolvedDbHost,
    'DB_PORT' => $resolvedDbPort,
    'DB_DATABASE' => $finalDbDatabase,
    'DB_USERNAME' => $resolvedDbUsername,
    'DB_PASSWORD' => $resolvedDbPassword,
    'DB_SSLMODE' => getenv('DB_SSLMODE') ?: 'require',
    'SUPABASE_PROJECT_REF' => 'fnceytkzkowaomjknqet',
    'SUPABASE_URL' => getenv('SUPABASE_URL') ?: 'https://fnceytkzkowaomjknqet.supabase.co',
    'VIEW_COMPILED_PATH' => '/tmp/views',
    'SESSION_DRIVER' => getenv('SESSION_DRIVER') ?: 'cookie',
    'SESSION_ENCRYPT' => 'true',
    'CACHE_STORE' => getenv('CACHE_STORE') ?: 'array',
    'LOG_CHANNEL' => 'stderr',
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php',
];

foreach (['APP_KEY', 'DB_URL', 'POSTGRES_URL', 'DATABASE_URL', 'SUPABASE_PROJECT_REF', 'SUPABASE_URL', 'SUPABASE_ANON_KEY'] as $passthroughVar) {
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

// 5. In-process self-healing migration and seeder fallback if database is fresh
if ($needsSeed && ! $useSupabasePostgres) {
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

// 6. Delegate execution to Laravel public index
require __DIR__.'/../public/index.php';
