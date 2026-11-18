<?php

use Illuminate\Support\Str;
use Pdo\Mysql;
use Pdo\Pgsql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // DB_POOLER=pgbouncer sends this connection through PgBouncer in transaction mode (docker-compose.yml: only the
        // php-fpm web container; migrations, queue workers, scheduler, Reverb and backups stay on DB_HOST directly).
        // Unset or `direct` = straight to DB_HOST, as before. Under transaction pooling consecutive statements of one
        // request may run on different server connections, so PDO must not create named prepared statements
        // (PDO prepares `pdo_stmt_N` on one server connection and executes it on another: "prepared statement does
        // not exist"). PGSQL_ATTR_DISABLE_PREPARES sends each query with its parameters in one PQexecParams call
        // (unnamed statement, parameters still typed by the server exactly as with native prepares), unlike
        // ATTR_EMULATE_PREPARES, which inlines values as SQL literals and breaks `boolean = 1`. Session-scoped
        // features (session advisory locks, LISTEN/NOTIFY, session SET, temp tables, WITH HOLD cursors) do not survive
        // transaction pooling: tests/Unit/TransactionPoolingSafetyTest.php fails when one appears. docs/OPERATIONS.md
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_POOLER') === 'pgbouncer' ? env('DB_POOL_HOST', '127.0.0.1') : env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_POOLER') === 'pgbouncer' ? env('DB_POOL_PORT', '6432') : env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // A session SET (Laravel runs it on connect): harmless under transaction pooling only because every client
            // sets the same value, so whichever server connection a statement lands on has it
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'pooler' => env('DB_POOLER') === 'pgbouncer' ? 'pgbouncer' : 'direct',
            'options' => extension_loaded('pdo_pgsql') ? [
                (PHP_VERSION_ID >= 80400 ? Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES) => env('DB_POOLER') === 'pgbouncer' || (bool) env('DB_DISABLE_PREPARES', false),
            ] : [],
        ],

        // M4 (docs/scale/M4_DESIGN.md section 5): the role that owns the schema (rizqim_owner) for migrations and the
        // partition commands; the application itself runs as DB_USERNAME (DML only). Always direct, never the pooler.
        // No credentials here on purpose: `config:cache` would write them into bootstrap/cache/config.php, which every
        // php-fpm worker reads. App\Support\Database\OwnerConnection::prepare() fills username and password from the
        // process environment (DB_OWNER_USERNAME / DB_OWNER_PASSWORD) when such a command starts. Unset = one role.
        'pgsql_owner' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => null,
            'password' => null,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'pooler' => 'direct',
            // pdo_pgsql passes ATTR_TIMEOUT as libpq connect_timeout: the monitoring check that logs in as the owner
            // every minute (PartitionsCheck) never waits long for a server that does not answer
            'options' => extension_loaded('pdo_pgsql') ? [PDO::ATTR_TIMEOUT => 5] : [],
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    | M4 stage 3 (docs/scale/M4_DESIGN.md 4.3, docs/OPERATIONS.md section 24): row-level security for tenant requests.
    | TENANT_RLS=1: every transaction of a tenant-bound request runs as the role DB_TENANT_ROLE (rizqim_tenant, created
    | by scripts/db-roles.sh) with the request's store in the setting app.store_id, so PostgreSQL itself refuses rows of
    | another store on the tables of App\Support\Tenancy\TenantRls::TABLES. Unset or 0 = no role switch: the
    | application guard and the store scope alone, exactly as before stage 3 (the policies bind only that role).
    | Needs the split roles (DB_OWNER_USERNAME): `php artisan app:check-production` refuses a setup that cannot switch.
    */

    'tenant_rls' => [
        'enabled' => (bool) env('TENANT_RLS', false),
        'role' => env('DB_TENANT_ROLE', 'rizqim_tenant'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
