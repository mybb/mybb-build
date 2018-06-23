#!/bin/sh

# Place deploy keys, fetch public keys
if [ -d "/home/user/secrets/" ]; then
    cp -r /home/user/secrets/. /home/user/.ssh
    chmod 0700 /home/user/.ssh
    chmod -R 0600 /home/user/.ssh/*

    ssh-keyscan -t rsa github.com >> /home/user/.ssh/known_hosts
fi

# Save output and move to the re-generated directory
phing $1 2>&1 | tee build.log && mv build.log output/build.log
