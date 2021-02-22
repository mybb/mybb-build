<?php
declare(strict_types=1);

$args = getopt('', [
    'varyingEolEncodingFiles:',
]);

$files = explode(',', $args['varyingEolEncodingFiles']);

foreach ($files as $file) {
    file_put_contents(
        $file,
        preg_replace(
            "/\r\n|\r/",
            "\n",
            file_get_contents($file)
        )
    );
}
