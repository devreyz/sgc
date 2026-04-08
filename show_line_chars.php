<?php
$f = file('compiled_debug.php');
$line = isset($f[165]) ? $f[165] : '';
for ($i=0;$i<strlen($line);$i++){
    $c = $line[$i];
    $ord = ord($c);
    printf("%03d: '%s' (%d)\n", $i+1, ($c=="\n"?"\\n":($c=="\r"?"\\r":$c)), $ord);
}

echo "\nLINE RAW: \n" . $line;
