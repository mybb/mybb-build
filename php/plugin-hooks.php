<?php
declare(strict_types=1);

$args = getopt('', [
    'distSetSourceDirectory:',
    'targetVersionCode:',
    'distPluginHooksFile:',
]);

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

$hooks = [];

$realpath = realpath($args['distSetSourceDirectory']);

$pattern = '/\$plugins->run_hooks\((\'|")(?<name>[a-zA-Z0-9_]+)\1(?:, ?(?<parameters>[^)]*))*\)/';

$recursiveDirectoryIterator = new RecursiveDirectoryIterator($realpath);
$recursiveIteratorIterator = new RecursiveIteratorIterator($recursiveDirectoryIterator);

foreach ($recursiveIteratorIterator as $fileInfo) {
    if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
        $pathNormalized = str_replace($realpath . DIRECTORY_SEPARATOR, null, $fileInfo->getRealPath());
        $pathNormalized = str_replace('\\', '/', $pathNormalized);

        $fileHandle = fopen($fileInfo->getRealPath(), 'r');

        if ($fileHandle) {
            $lineNumber = 0;

            while (($lineContent = fgets($fileHandle, 4096)) !== false) {
                $lineNumber++;

                $result = preg_match($pattern, $lineContent, $matches);

                if ($result) {
                    if (isset($matches['parameters'])) {
                        $parameters = explode(',', $matches['parameters']);

                        array_walk($parameters, 'trim');
                    } else {
                        $parameters = [];
                    }

                    $hooks[] = [
                        'name' => $matches['name'],
                        'file' => $pathNormalized,
                        'line' => $lineNumber,
                        'parameters' => $parameters,
                    ];
                }
            }

            fclose($fileHandle);
        }
    }
}

usort($hooks, function ($a, $b) {
    $pathComparison = directoryStructureSort($a['file'], $b['file']);

    if ($pathComparison === 0) {
        return $a['line'] - $b['line'];
    } else {
        return $pathComparison;
    }
});

$hooksYml = <<<YML
hooks:

YML;

foreach ($hooks as $hook) {
    if ($hook['parameters']) {
        $parametersYml = <<<YML
    parameters:

YML;

        foreach ($hook['parameters'] as $parameter) {
            $parameterEscaped = addcslashes($parameter, '"');

            $parametersYml .= <<<YML
      - "{$parameterEscaped}"

YML;
        }
    } else {
        $parametersYml = '';
    }

    $hooksYml .= <<<YML
  - name: {$hook['name']}
    file: {$hook['file']}
    line: {$hook['line']}
{$parametersYml}
YML;
}

$yml = <<<YML
version_code: {$args['targetVersionCode']}
{$hooksYml}
YML;

file_put_contents($args['distPluginHooksFile'], $yml);
