<?php
$file = __DIR__ . '/../resources/views/delivery/project-deliveries.blade.php';
$content = file_get_contents($file);

$marker = "@section('content')";
$firstPos  = strpos($content, $marker);
$secondPos = strpos($content, $marker, $firstPos + 1);

if ($secondPos === false) {
    echo "No duplicate found.\n";
    exit(0);
}

// Keep only up to (but not including) the second @section('content')
$content = substr($content, 0, $secondPos);
// Trim trailing whitespace / newlines
$content = rtrim($content) . "\n";

file_put_contents($file, $content);
echo "Fixed. File now " . strlen($content) . " bytes\n";
