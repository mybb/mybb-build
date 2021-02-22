<?php

$args = getopt(null, [
    'standardFilesCsv:',
    'varyingEolEncodingFilesCsv:',
    'distSetSourceDirectory:',
    'distChecksumsFile:',
    'algorithm:',
]);

function directoryStructureSort($a, $b) {
    $aNesting = substr_count($a, '/');
    $bNesting = substr_count($b, '/');

    if ($aNesting == 0 && $bNesting == 0) {
        return strnatcmp($a, $b);
    } elseif ($aNesting == 0) {
        return 1;
    } elseif ($bNesting == 0) {
        return -1;
    } else {
        $aParents = array_slice(explode('/', $a), 0, -1);
        $bParents = array_slice(explode('/', $b), 0, -1);

        foreach ($aParents as $order => $name) {
            if (isset($bParents[$order])) {
                if ($name != $bParents[$order]) {
                    return strnatcmp($name, $bParents[$order]);
                }
            } else {
                return -1;
            }
        }

        if ($bNesting > $aNesting) {
            return 1;
        } else {
            return strnatcmp($a, $b);
        }
    }
}

function processFile($filePath, $varyingEolEncoding = false)
{
    global $args, $hashes;

    $content = file_get_contents($filePath);

    $fileHashes = [];

    $packageFilePath = str_replace($args['distSetSourceDirectory'] . '/', null, $filePath);

    if ($varyingEolEncoding) {
        $fileHashes[] = hash($args['algorithm'], str_replace(["\r\n", "\r"], "\n", $content));
        $fileHashes[] = hash($args['algorithm'], str_replace(["\r\n", "\r", "\n"], "\r\n", $content));
        $fileHashes[] = hash($args['algorithm'], str_replace(["\r\n", "\n"], "\r", $content));
    } else {
        $fileHashes[] = hash($args['algorithm'], $content);
    }

    $hashes[$packageFilePath] = $fileHashes;
}

$hashes = [];

$files = explode(',', $args['standardFilesCsv']);
$varyingEolEncodingFiles = explode(',', $args['varyingEolEncodingFilesCsv']);

foreach ($files as $file) {
    processFile($file);
}

foreach ($varyingEolEncodingFiles as $file) {
    processFile($file, true);
}

uksort($hashes, 'directoryStructureSort');

$lines = [];

foreach ($hashes as $filePath => $fileHashes) {
    foreach ($fileHashes as $hash) {
        $lines[] = $hash . ' ./' . $filePath;
    }
}

file_put_contents($args['distChecksumsFile'], implode("\n", $lines));
