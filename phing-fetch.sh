#!/bin/sh

hash sha512sum 2>/dev/null || {
    echo >&2 'Command "sha512" not found'
    exit 1
}

wget -q https://www.phing.info/get/phing-latest.phar -O phing-latest.phar
wget -q https://www.phing.info/get/phing-latest.phar.sha512 -O phing-latest.phar.sha512

sha512sum --quiet -c phing-latest.phar.sha512

if [ $? -ne 0 ]; then
    >&2 echo 'Signature for phing-latest.phar does not match'
    exit 1
fi

rm phing-latest.phar.sha512
exit 0
