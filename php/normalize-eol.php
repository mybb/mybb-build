<?php

$args = getopt(null, [
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
