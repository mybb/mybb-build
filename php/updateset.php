<?php

$args = getopt(null, [
    'distFilesCsv:',
    'previousFilesCsv:',
    'previousSourceDirectory:',
    'distSetSourceDirectory:',
    'outputDirectory:',
    'distChangedFilesFile:',
    'distRemovedFilesFile:',
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

        return 0;
    }
}

/*
function files_equal($file1Path, $file2Path)
{
    $blockSize = 8192;

    if (filesize($file1Path) == filesize($file2Path)) {
        $result = true;

        $file1Handle = fopen($file1Path, 'rb');
        $file2Handle = fopen($file2Path, 'rb');

        $file1Block = fread($file1Handle, $blockSize);

        while ($file1Block !== false) {
            $file1Block = fread($file2Handle, $blockSize);

            if ($file1Block !== $file2Block) {
                $result = false;
                break;
            }
        }

        fclose($file1Path);
        fclose($file2Path);

        return $result;
    }

    return false;
}
*/
$fileList = [];
$changedFileList = [];

$files = explode(',', $args['distFilesCsv']);
$previousFiles = explode(',', $args['previousFilesCsv']);

// copy changed files
$sourceEolCharacters = ["\r\n", "\r", "\n"];
$targetEolCharacter = "\n";

foreach ($files as $filePath) {
    $copy = false;

    $packageFilePath = str_replace(realpath($args['distSetSourceDirectory']) . '/', null, realpath($filePath));

    $fileList[] = $packageFilePath;

    $oldFilePath = $args['previousSourceDirectory'] . '/' . $packageFilePath;
    $newFilePath = $args['distSetSourceDirectory'] . '/' . $packageFilePath;

    if (is_file($oldFilePath)) {
        $oldContentNormalized = str_replace($sourceEolCharacters, $targetEolCharacter, file_get_contents($oldFilePath));
        $newContentNormalized = str_replace($sourceEolCharacters, $targetEolCharacter, file_get_contents($newFilePath));

        if ($oldContentNormalized !== $newContentNormalized) {
            $copy = true;
        }
    } else {
        $copy = true;
    }

    if ($copy) {
        $changedFileList[] = $packageFilePath;
    }
}

// save a list of changed files
if (!empty($changedFileList)) {
    usort($changedFileList, 'directoryStructureSort');
    file_put_contents($args['distChangedFilesFile'], implode("\n", $changedFileList));
}

// save a list of removed files
$RecursiveDirectoryIterator = new RecursiveDirectoryIterator(realpath($args['previousSourceDirectory']));
$RecursiveIteratorIterator = new RecursiveIteratorIterator($RecursiveDirectoryIterator);

foreach ($RecursiveIteratorIterator as $filePath) {
    if (is_file($filePath)) {
        $previousPackageFilePath = str_replace(realpath($args['previousSourceDirectory']) . '/', null, realpath($filePath));

        if (!in_array($previousPackageFilePath, $fileList)) {
            $removedFileList[] = $previousPackageFilePath;
        }
    }
}

if ($removedFileList) {
    file_put_contents($args['distRemovedFilesFile'], implode("\n", $removedFileList));
}
