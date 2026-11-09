<?php

use Symfony\Component\Yaml\Yaml;

it('restricts the local database and requires passwords', function () {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/compose.yaml');
    $app = $compose['services']['laravel.test'];
    $mysql = $compose['services']['mysql'];

    expect($app['ports'])->toContain('127.0.0.1:${APP_PORT:-8000}:80');
    expect($app['ports'])->toContain('127.0.0.1:${VITE_PORT:-5173}:${VITE_PORT:-5173}');
    expect($mysql['ports'])->toContain('127.0.0.1:${DB_PORT:-3306}:3306');
    expect($mysql['environment']['MYSQL_ROOT_HOST'])->toBe('localhost');
    expect($mysql['environment'])->not->toHaveKey('MYSQL_ALLOW_EMPTY_PASSWORD');
    expect($mysql['environment']['MYSQL_ROOT_PASSWORD'])->toStartWith('${DB_ROOT_PASSWORD:?');
    expect($mysql['environment']['MYSQL_PASSWORD'])->toStartWith('${DB_PASSWORD:?');
});

it('keeps production http private and enforces secure sessions', function () {
    $root = dirname(__DIR__, 2);
    $compose = Yaml::parseFile($root.'/compose.prod.yaml');
    $app = $compose['services']['app'];
    $web = $compose['services']['web'];
    $mysql = $compose['services']['mysql'];

    expect($web['ports'])->toContain('127.0.0.1:${WEB_PORT:-8080}:8080');
    expect($web['user'])->toBe('101:101');
    expect($mysql)->not->toHaveKey('ports');
    expect($mysql['environment']['MYSQL_PASSWORD'])->toStartWith('${DB_PASSWORD:?');
    expect($mysql['environment']['MYSQL_ROOT_PASSWORD'])->toStartWith('${DB_ROOT_PASSWORD:?');
    expect($mysql['environment']['MYSQL_ROOT_HOST'])->toBe('localhost');
    expect($mysql)->not->toHaveKey('env_file');
    expect($app['environment']['DB_ROOT_PASSWORD'])->toBe('');
    expect($app['environment']['SESSION_SECURE_COOKIE'])->toBe('true');
    expect($app['environment']['SESSION_HTTP_ONLY'])->toBe('true');
    expect($app['environment']['SESSION_ENCRYPT'])->toBe('true');
    expect($app['cap_drop'])->toBe(['ALL']);
    expect($app['cap_add'])->toBe(['CHOWN', 'DAC_OVERRIDE', 'SETGID', 'SETUID']);
    expect($web['cap_drop'])->toBe(['ALL']);
    expect(file_get_contents($root.'/docker/prod/nginx/default.conf'))
        ->toContain('listen 8080;')
        ->toContain('fastcgi_param HTTPS on;');
    expect(file_get_contents($root.'/docker/prod/entrypoint.sh'))
        ->toContain('php artisan config:cache'.PHP_EOL)
        ->not->toContain('php artisan config:cache >/dev/null');
});
