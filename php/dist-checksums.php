<?php

$args = getopt(null, [
    'standardFilesCsv:',
    'varyingEolEncodingFilesCsv:',
    'distSetSourceDirectory:',
    'distChecksumsFile:',
    'algorithm:',
]);

$hashes = [];

function processFile($filePath, $varyingEolEncoding = false)
{
    global $args, $hashes;

    $content = file_get_contents($filePath);

    $fileHashes = [
        'original' => hash($args['algorithm'], $content),
    ];

    $packageFilePath = str_replace($args['distSetSourceDirectory'] . '/', null, $filePath);

    if ($varyingEolEncoding) {
        $fileHashes[] = hash($args['algorithm'], str_replace(["\r\n", "\r"], "\n", $content));
        $fileHashes[] = hash($args['algorithm'], str_replace(["\r\n", "\r", "\n"], "\r\n", $content));
        $fileHashes[] = hash($args['algorithm'], str_replace(["\r\n", "\n"], "\r", $content));
    }

    $hashes[$packageFilePath] = $fileHashes;
}

$files = explode(',', $args['standardFilesCsv']);
$varyingEolEncodingFiles = explode(',', $args['varyingEolEncodingFilesCsv']);

foreach ($files as $file) {
    processFile($file);
}

foreach ($varyingEolEncodingFiles as $file) {
    processFile($file, true);
}

ksort($files);

$lines = [];

foreach ($hashes as $filePath => $fileHashes) {
    foreach ($fileHashes as $hash) {
        $lines[] = $hash . ' ./' . $filePath;
    }
}

file_put_contents($args['distChecksumsFile'], implode("\n", $lines));
