<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$path = resource_path('views/pdf/project-associate-receipt.blade.php');
$contents = file_get_contents($path);
$compiler = app('blade.compiler');
$compiled = $compiler->compileString($contents);
file_put_contents(__DIR__ . '/compiled_debug.php', $compiled);
echo "Wrote compiled_debug.php\n";
