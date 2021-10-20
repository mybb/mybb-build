<?php
declare(strict_types=1);

$args = getopt('', [
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
    'issuesRepository:',
    'resolvedIssuesMilestone:',
    'resolvedIssuesLink:',
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

function directoryStructureSort($a, $b)
{
    $aNesting = substr_count($a, '/');
    $bNesting = substr_count($b, '/');

    if ($aNesting === 0 && $bNesting === 0) {
        return strnatcmp($a, $b);
    } elseif ($aNesting === 0) {
        return 1;
    } elseif ($bNesting === 0) {
        return -1;
    } else {
        $aParents = array_slice(explode('/', $a), 0, -1);
        $bParents = array_slice(explode('/', $b), 0, -1);

        foreach ($aParents as $order => $name) {
            if (isset($bParents[$order])) {
                if ($name !== $bParents[$order]) {
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

function fetchJsonPaged($ch, $path, $sep, $callback, $perPage=100) {
    $results = [];
    $page = 1;

    $curlopt = [
        CURLOPT_HEADER => 1,
    ] + DEFAULT_CURLOPTS;

    while ($page !== null) {
        $curlopt[CURLOPT_URL] = $path . $sep . 'per_page=' . $perPage . '&page=' . $page;

        curl_setopt_array($ch, $curlopt);

        $response = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $data = json_decode($body, true);

        if ($data === null) {
            break;
        }

        foreach ($data as $item) {
            $result = $callback($item);
            if ($result === null) {
                continue;
            }

            $results[] = $result;
        }

        preg_match('/^Link:(.*?)&page=([0-9]+)>; rel="next"/im', $header, $matches);

        if ($matches) {
            $page = $matches[2];
        } else {
            $page = null;
        }
    }

    return $results;
}

function getGithubIssueAges($ch, $repo, $resolvedIssuesMilestone) {
    $githubApiPath = 'https://api.github.com/repos/' . $repo . '/';

    $milestones = fetchJsonPaged($ch, $githubApiPath . 'milestones?state=all', '&', function($item) use ($resolvedIssuesMilestone) {
        if ($item['title'] === $resolvedIssuesMilestone) {
            return $item;
        }

        return null;
    });

    if (empty($milestones[0])) {
        return null;
    }

    $path = $githubApiPath . 'issues?milestone=' . $milestones[0]['number'] . '&state=closed';

    $issueAges = fetchJsonPaged($ch, $path, '&', function($item) {
        if (empty($item['closed_at']) || !empty($item['pull_request'])) {
            return null;
        }

        $created = DateTime::createFromFormat(DateTime::ISO8601, $item['created_at']);
        $closed = DateTime::createFromFormat(DateTime::ISO8601, $item['closed_at']);

        if ($created === false || $closed === false) {
            return null;
        }

        $interval = $created->diff($closed);
        return (int) $interval->format('%a');
    });

    return $issueAges;
}

function getIssuesData($ch, $issuesRepository, $resolvedIssuesMilestone) {
    $parsed = parse_url($issuesRepository);

    if ($parsed === false) {
        return null;
    }

    $repo = trim(preg_replace('/\.git$/', '', $parsed['path']), '/');

    switch ($parsed['host']) {
        case 'github.com':
            $issueAges = getGithubIssueAges($ch, $repo, $resolvedIssuesMilestone);
        break;
        default:
            return null;
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

if ($realpath !== false && is_dir($realpath)) {
    $count = 0;

    $recursiveDirectoryIterator = new RecursiveDirectoryIterator($realpath);
    $recursiveIteratorIterator = new RecursiveIteratorIterator($recursiveDirectoryIterator);

    foreach ($recursiveIteratorIterator as $fileInfo) {
        if ($fileInfo->isFile()) {
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
$resolvedIssuesYml = '';

$ch = curl_init();

$issuesData = getIssuesData($ch, $args['issuesRepository'], $args['resolvedIssuesMilestone']);

if ($issuesData !== null) {
    $resolvedIssuesYml .= <<<YML
resolved_issues_number: "{$issuesData['numIssues']}"
resolved_issues_age_median: "{$issuesData['medianAge']}"
resolved_issues_age_mean: "{$issuesData['meanAge']}"

YML;
}

$resolvedIssuesYml .= <<<YML
resolved_issues_link: {$args['resolvedIssuesLink']}"


YML;

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
