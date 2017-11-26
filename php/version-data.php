<?php

$args = getopt(null, [
    'buildDirectory:',
    'distChangedFilesFile:',
    'distRemovedFilesFile:',
    'distChangedTemplatesFile:',
    'updateSetDirectory:',
    'languageFilesPackageDirectory:',
    'distSetName:',
    'updateSetName:',
    'targetVersion:',
    'targetVersionCode:',
    'distVersionDataFile:',
]);

function arrayToYml($array, $level = 1)
{
    $output = null;

    foreach ($array as $key => $value) {
        $output .= str_repeat('  ', $level) . '- ';

        if (is_array($value)) {
            $output .= $key . ":\n";
            $output .= arrayToYml($value, $level + 1);
        } else {
            $output .= $value . "\n";
        }
    }

    return $output;
}

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

// changed files YML
$file = $args['distChangedFilesFile'];

$changedFilesYml = null;

if (file_exists($file)) {
    $lines = file($file);

    usort($lines, 'directoryStructureSort');

    $array = [];

    foreach ($lines as $path) {
        $parents = array_filter(explode('/', $path));
        $file = trim(array_pop($parents));

        $immediateParent = &$array;
        foreach ($parents as $parent) {
            $immediateParent = &$immediateParent[$parent];
        }

        $immediateParent[] = $file;
    }

    $changedFilesYml = <<<YML

changed_files:

YML;

    $changedFilesYml .= arrayToYml($array);
}

// removed files YML
$file = $args['distRemovedFilesFile'];

$removedFilesYml = null;

if (file_exists($file)) {
    $lines = file($file);

    usort($lines, 'directoryStructureSort');

    $array = [];

    foreach ($lines as $path) {
        $parents = explode('/', $path);
        $file = trim(array_pop($parents));

        $immediateParent = &$array;
        foreach ($parents as $parent) {
            $immediateParent = &$immediateParent[$parent];
        }

        $immediateParent[] = $file;
    }

    $removedFilesYml = <<<YML

removed_files:

YML;

    $removedFilesYml .= arrayToYml($array);
}

// changed templates YML
$changedTemplatesYml = null;

$changedTemplatesFile = $args['distChangedTemplatesFile'];

if (file_exists($changedTemplatesFile)) {
    $changedTemplates = file($changedTemplatesFile);

    $changedTemplatesYml = <<<YML

changed_templates:

YML;

    foreach ($changedTemplates as $value) {
        $value = trim($value);

        $changedTemplatesYml .= <<<YML
  - {$value}

YML;
    }
}

// changed language files YML
$changedLanguageFilesNumberYml = null;

$realpath = realpath($args['updateSetDirectory'] . '/' . $args['languageFilesPackageDirectory']);

if (is_dir($realpath)) {
    $count = 0;

    $RecursiveDirectoryIterator = new RecursiveDirectoryIterator($realpath);
    $RecursiveIteratorIterator = new RecursiveIteratorIterator($RecursiveDirectoryIterator);

    foreach ($RecursiveIteratorIterator as $path) {
        if (is_file($path)) {
            $count++;
        }
    }

    if ($count) {
        $changedLanguageFilesNumberYml = <<<YML
changed_language_files_number: "{$count}"

YML;
    }
}

// packages YML
$distSetPackageSize = round(filesize($args['buildDirectory'] . '/' . $args['distSetName'].'.zip') / 1024 / 1024, 2);
$updateSetPackageSize = round(filesize($args['buildDirectory'] . '/' . $args['updateSetName'].'.zip') / 1024 / 1024, 2);

$distSetPackageChecksumsYml = null;

$filePath = $args['buildDirectory'] . '/' .$args['distSetName'].'.zip.checksums';

if (file_exists($filePath)) {
    $lines = file($filePath);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line) {
            $data = explode(' ', $line);
            $distSetPackageChecksumsYml .= <<<YML
          - type: {$data[0]}
            value: {$data[1]}

YML;
        }
    }
}

$updateSetPackageChecksumsYml = null;

$filePath = $args['buildDirectory'] . '/' . $args['updateSetName'] . '.zip.checksums';

if (file_exists($filePath)) {
    $lines = file($filePath);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line) {
            $data = explode(' ', $line);
            $updateSetPackageChecksumsYml .= <<<YML
          - type: {$data[0]}
            value: {$data[1]}

YML;
        }
    }
}

// stub YML
$yml = <<<YML
---
title: "Version {$args['targetVersion']}"

version_number: "{$args['targetVersion']}"
version_code: "{$args['targetVersionCode']}"

packages:
  - type: mybb
    formats:
      - type: zip
        filesize: "{$distSetPackageSize} MB"
        checksums:
{$distSetPackageChecksumsYml}
  - type: changed_files
    formats:
      - type: zip
        filesize: "{$updateSetPackageSize} MB"
        checksums:
{$updateSetPackageChecksumsYml}
{$changedLanguageFilesNumberYml}{$changedFilesYml}{$removedFilesYml}{$changedTemplatesYml}
---
YML;

file_put_contents($args['distVersionDataFile'], $yml);
