<?php

$delay = isset($argv[1]) ? $argv[1] : 10000;

$buffer = array();
while ($s = fgets(STDIN)) {
    $s = trim($s);
    echo "$s\n";
    array_push($buffer, $s);
    
    if (count($buffer) > $delay) {
        $b = array_shift($buffer);
        echo "$b\n";
    }
}

// finish off any buffered
foreach ($buffer as $b) {
    echo "$b\n";
}