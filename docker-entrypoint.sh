#!/bin/sh

# Save output and move to the re-generated directory
phing $1 2>&1 | tee build.log && mv build.log output/build.log
