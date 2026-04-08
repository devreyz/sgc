<?php
$f = file('compiled_debug.php');
for ($i = 160; $i <= 175; $i++) {
    $ln = isset($f[$i]) ? $f[$i] : '';
    echo ($i+1) . ": " . $ln;
}
