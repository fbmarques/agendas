<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    $index = public_path('index.html');

    if (! file_exists($index)) {
        return response(
            "<!DOCTYPE html><html><body><h1>Frontend not built.</h1>"
            ."<p>Run <code>cd front &amp;&amp; npm run build</code>.</p></body></html>",
            200
        );
    }

    return response(file_get_contents($index), 200)
        ->header('Content-Type', 'text/html');
})->where('any', '^(?!api|up).*$');
