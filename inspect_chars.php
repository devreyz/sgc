<?php
$path = 'resources/views/pdf/project-associate-receipt.blade.php';
$contents = file_get_contents($path);
$pos = strpos($contents, "</strong>@if(");
if ($pos === false) $pos = strpos($contents, "</strong>");
$start = max(0, $pos - 20);
$snippet = substr($contents, $start, 80);
for ($i=0;$i<strlen($snippet);$i++){
    $c = $snippet[$i];
    $ord = ord($c);
    echo $i+1 . ": '" . ($c === "\n" ? "\\n" : ($c === "\r" ? "\\r" : $c)) . "' (".$ord.")\n";
}
echo "\nSNIPPET: ";
var_export($snippet);
