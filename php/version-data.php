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
    'githubRepository:',
    'githubMilestoneNumber:',
    'distVersionDataFile:',
]);

define('MY_USERAGENT', 'mybb/mybb-build');
define('DEFAULT_CURLOPTS', [
    CURLOPT_USERAGENT => MY_USERAGENT,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_SSL_VERIFYPEER => 1,
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

        return strnatcmp($a, $b);
    }
}

function fetchJson($ch, $path) {
    $curlopt = [
        CURLOPT_URL => $path,
    ] + DEFAULT_CURLOPTS;

    curl_setopt_array($ch, $curlopt);

    $response = curl_exec($ch);

    $data = json_decode($response, true);

    if ($data === null) {
        return null;
    }

    return $data;
}

function getIssueStats($ch, $githubApiPath, $milestoneNumber) {
    $issueAges = [];
    $page = 1;

    $curlopt = [
        CURLOPT_HEADER => 1,
    ] + DEFAULT_CURLOPTS;

    while ($page !== null) {
        $curlopt[CURLOPT_URL] = $githubApiPath . 'issues?milestone=' . $milestoneNumber . '&state=closed&per_page=100&page=' . $page;

        curl_setopt_array($ch, $curlopt);

        $response = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $issues = json_decode($body, true);

        if ($issues === null) {
            break;
        }

        foreach ($issues as $issue) {
            if (empty($issue['closed_at']) || !empty($issue['pull_request'])) {
                continue;
            }
            $created = DateTime::createFromFormat(DateTime::ISO8601, $issue['created_at']);
            $closed = DateTime::createFromFormat(DateTime::ISO8601, $issue['closed_at']);

            if ($created === false || $closed === false) {
                continue;
            }

            $interval = $created->diff($closed);
            $issueAges[] = (int) $interval->format('%a');
        }

        preg_match('/^Link:(.*?)&page=([0-9]+)>; rel="next"/im', $header, $matches);

        if ($matches) {
            $page = $matches[2];
        } else {
            $page = null;
        }
    }

    sort($issueAges);

    $numIssues = count($issueAges);

    if ($numIssues == 0) {
        return null;
    }

    if ($numIssues % 2 === 0) {
        $median = ($issueAges[($numIssues/2)-1] + $issueAges[$numIssues/2]) / 2;
    } else {
        $median = $issueAges[intdiv($numIssues, 2)];
    }

    return [
        'medianAge' => $median,
        'meanAge' => round(array_sum($issueAges) / $numIssues, 1),
        'numIssues' => $numIssues,
    ];
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

// resolved issue YML
$resolvedIssuesYml = null;

$ch = curl_init();
$githubApiPath = 'https://api.github.com/repos/' . $args['githubRepository'] . '/';

if (empty($args['githubMilestoneNumber']) || ($milestone = fetchJson($ch, $githubApiPath . 'milestones/' . $args['githubMilestoneNumber'])) === null) {
    // Attempt to determine milestone number ourselves
    $milestones = fetchJson($ch, $githubApiPath . 'milestones?state=all');
    if ($milestones !== null) {
        foreach ($milestones as $m) {
            if (!empty($m['title']) && $m['title'] === $args['targetVersion']) {
                $milestone = $m;
                break;
            }
        }
    }
}

if ($milestone !== null) {
    // We have a correct milestone
    $resolvedIssuesYml = '';

    $issueStats = getIssueStats($ch, $githubApiPath, $milestone['number']);

    if ($issueStats !== null) {
        $resolvedIssuesYml .= <<<YML
resolved_issues_number: "{$issueStats['numIssues']}"
resolved_issues_age_median: "{$issueStats['medianAge']}"
resolved_issues_age_mean: "{$issueStats['meanAge']}"

YML;
    }

    $resolvedIssuesMilestone = urlencode($milestone['title']);

    $resolvedIssuesYml .= <<<YML
resolved_issues_link: "https://github.com/{$args['githubRepository']}/issues?q=is%3Aissue%20is%3Aclosed%20label%3As%3Aresolved%20milestone%3A{$resolvedIssuesMilestone}"


YML;
}

curl_close($ch);

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
{$resolvedIssuesYml}{$changedLanguageFilesNumberYml}{$changedFilesYml}{$removedFilesYml}{$changedTemplatesYml}
---
YML;

file_put_contents($args['distVersionDataFile'], $yml);
