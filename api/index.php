<?php

declare(strict_types=1);

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
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// 2. Prepare writable SQLite database
$sourceDb = __DIR__ . '/../database/database.sqlite';
if (!file_exists($sourceDb) || filesize($sourceDb) === 0) {
    $sourceDb = __DIR__ . '/../.vscode/ptp/database/database.sqlite';
}
$targetDb = '/tmp/database.sqlite';
$needsSeed = false;

if (!file_exists($targetDb) || filesize($targetDb) === 0) {
    if (file_exists($sourceDb) && filesize($sourceDb) > 0) {
        @copy($sourceDb, $targetDb);
    } else {
        @touch($targetDb);
        $needsSeed = true;
    }
    @chmod($targetDb, 0666);
}

// 3. Set environment variables for serverless execution across putenv, $_ENV, and $_SERVER
$useSupabasePostgres = extension_loaded('pdo_pgsql') && (getenv('DB_CONNECTION') !== 'sqlite');

$resolvedDbConnection = $useSupabasePostgres ? 'pgsql' : 'sqlite';
$resolvedDbHost = getenv('DB_HOST') ?: 'aws-0-ap-northeast-1.pooler.supabase.com';
$resolvedDbPort = getenv('DB_PORT') ?: '5432';
$resolvedDbDatabase = $useSupabasePostgres ? (getenv('DB_DATABASE') ?: 'postgres') : $targetDb;
$resolvedDbUsername = getenv('DB_USERNAME') ?: 'spktad_app.fqewatvykleqxnwmmiff';
$resolvedDbPassword = getenv('DB_PASSWORD') ?: 'Spktad_Pelindo_Cirebon_2026!Secure';

$serverlessEnv = [
    'VERCEL' => '1',
    'APP_NAME' => 'SPKTAD Pelindo Cirebon',
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_KEY' => getenv('APP_KEY') ?: 'base64:fA3eoib3fK3pvs6TACojMNSSwg2TWvuJ892sQ3KuSCQ=',
    'APP_TIMEZONE' => 'Asia/Jakarta',
    'APP_LOCALE' => 'id',
    'DB_CONNECTION' => $resolvedDbConnection,
    'DB_HOST' => $resolvedDbHost,
    'DB_PORT' => $resolvedDbPort,
    'DB_DATABASE' => $resolvedDbDatabase,
    'DB_USERNAME' => $resolvedDbUsername,
    'DB_PASSWORD' => $resolvedDbPassword,
    'DB_SSLMODE' => getenv('DB_SSLMODE') ?: 'require',
    'SUPABASE_PROJECT_REF' => 'fqewatvykleqxnwmmiff',
    'SUPABASE_URL' => getenv('SUPABASE_URL') ?: 'https://fqewatvykleqxnwmmiff.supabase.co',
    'VIEW_COMPILED_PATH' => '/tmp/views',
    'SESSION_DRIVER' => 'cookie',
    'SESSION_ENCRYPT' => 'true',
    'CACHE_STORE' => 'array',
    'LOG_CHANNEL' => 'stderr',
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php',
];

foreach ($serverlessEnv as $key => $val) {
    putenv("{$key}={$val}");
    $_ENV[$key] = $val;
    $_SERVER[$key] = $val;
}

// 4. In-process self-healing migration and seeder fallback if database is fresh
if ($needsSeed) {
    $autoloadPath = file_exists(__DIR__ . '/../vendor/autoload.php')
        ? __DIR__ . '/../vendor/autoload.php'
        : __DIR__ . '/../.vscode/ptp/vendor/autoload.php';
    $bootstrapPath = file_exists(__DIR__ . '/../bootstrap/app.php')
        ? __DIR__ . '/../bootstrap/app.php'
        : __DIR__ . '/../.vscode/ptp/bootstrap/app.php';

    if (file_exists($autoloadPath) && file_exists($bootstrapPath)) {
        require $autoloadPath;
        $app = require_once $bootstrapPath;
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\ProductionSeeder', '--force' => true]);
        $app->handleRequest(Illuminate\Http\Request::capture());
        return;
    }
}

// 5. Delegate execution to Laravel public index
if (file_exists(__DIR__ . '/../public/index.php')) {
    require __DIR__ . '/../public/index.php';
} elseif (file_exists(__DIR__ . '/../.vscode/ptp/public/index.php')) {
    require __DIR__ . '/../.vscode/ptp/public/index.php';
}
