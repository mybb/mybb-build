<?php

$args = getopt(null, [
    'path:',
    'algorithms:',
]);

$lines = [];

$algorithms = explode(',', $args['algorithms']);

$hashingContexts = [];

foreach ($algorithms as $algorithm) {
    $hashingContexts[$algorithm] = hash_init($algorithm);
}

$fileHandle = fopen($args['path'], 'rb');

while (!feof($fileHandle)) {
    $block = fread($fileHandle, 8192);

    foreach ($algorithms as $algorithm) {
        hash_update($hashingContexts[$algorithm], $block);
    }
}

fclose($fileHandle);

foreach ($algorithms as $algorithm) {
    $checksum = hash_final($hashingContexts[$algorithm]);
    $lines[] = $algorithm . ' ' . $checksum;
}

file_put_contents($args['path'] . '.checksums', implode("\n", $lines));
