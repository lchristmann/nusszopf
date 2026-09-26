#!/bin/sh
# Turns the telling lines of a log into one GitHub annotation. A job's log needs a GitHub login to read; annotations do
# not, and the first failures of the release gate could not be told apart without them (P-16, P16-06).
#
#   sh scripts/ci-annotate.sh <title> <logfile>
set -u
title="$1"
log="$2"
if [ ! -f "$log" ]; then
    echo "::error title=$title::no $log: the step stopped before it wrote one"
    exit 0
fi
text="$(
    {
        grep -E '✘|^ +[0-9]+\) \[|^ +Error:|[0-9]+ (failed|flaky)' "$log" | head -n 20
        echo '...'
        tail -n 12 "$log"
    } | sed 's/\x1b\[[0-9;]*m//g' | cut -c1-260 | sed 's/%/%25/g' | awk '{printf "%s%%0A", $0}'
)"
echo "::error title=$title::$(printf '%s' "$text" | head -c 3800)"
