<?php
/**
 * Test dedupe filter
 * 
 * Reads from STDIN and dedupes lines. Can use with stream-dupe.php which will
 * introduce duplicates.
 * 
 * Pefect probability of hash collision = 1/N where N is number of cells.
 * Therefore chance of losing knowledge of some thing we've seen after 1
 * additional entry is 1/N. After 2, = 1/N + (N-1/N * 1/N). 
 */

include __DIR__ . '/../bootstrap.php';

$obf = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;

$m1 = memory_get_usage();
$t1 = microtime(TRUE);

$dupes = 0;
$processed = 0;

while ($s = fgets(STDIN)) {
    $s = trim($s);
    if (empty($s)) {
        continue;
    }
    
    $processed++;
    $seen = $obf->containsAndAdd(new nsqphp\Message\Message($s));
    if ($seen) {
        $dupes++;
    }
    echo ($seen ? 'SEEN' : ' -  ') . "\t$s\n";
}

$m2 = memory_get_usage();
$t2 = microtime(TRUE);

echo "\n\n";
echo "Processed\t$processed\n";
echo "Dupes\t$dupes\n";
echo "Memory\t" . sprintf('%0.2d', ($m2-$m1) / 1024) . "kb\n";
echo "Time\t" . sprintf('%0.2d', $t2-$t1) . "s\n";

/*
echo "\n\n";
$hc = $obf->getHashCollisions();
foreach ($hc as $index => $count) {
    echo "$index\t$count\n";
}
 * 
 */