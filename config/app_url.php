<?php

/*
|--------------------------------------------------------------------------
| CropSense Base URL
|--------------------------------------------------------------------------
|
| Local:
| http://localhost/CropSense/
|
| Production:
| https://cropsense.site/
|
*/

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$hostname = explode(':', $host)[0];

$isLocalhost = in_array(
    $hostname,
    [
        'localhost',
        '127.0.0.1'
    ],
    true
);

if (!defined('CROPSENSE_BASE_URL')) {
    define(
        'CROPSENSE_BASE_URL',
        $isLocalhost
            ? '/CropSense/'
            : '/'
    );
}


/*
|--------------------------------------------------------------------------
| URL Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('cropsense_url')) {

    function cropsense_url(string $path = ''): string
    {
        return CROPSENSE_BASE_URL . ltrim($path, '/');
    }
}


/*
|--------------------------------------------------------------------------
| Asset Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('cropsense_asset')) {

    function cropsense_asset(string $path): string
    {
        return cropsense_url(
            'assets/' . ltrim($path, '/')
        );
    }
}