<?php
declare(strict_types=1);

$args = getopt('', [
    'distSetSourceDirectory:',
    'targetVersionCode:',
    'distChangedTemplatesFile:',
]);

$templateNames = [];

$xml = simplexml_load_file($args['distSetSourceDirectory'] . '/install/resources/mybb_theme.xml');

foreach ($xml->templates[0]->template as $element) {
    $version = (string)$element->attributes()['version'];

    if ($version === $args['targetVersionCode']) {
        $name = $element->attributes()['name'];
        $templateNames[] = (string)$name;
    }
}

natsort($templateNames);

if ($templateNames) {
    file_put_contents($args['distChangedTemplatesFile'], implode("\n", $templateNames));
}
