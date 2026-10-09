#!/bin/sh
set -u

# Runs `bin/console` commands for one scheduled tick (a Cloud Run job execution;
# docs/runbooks/deployment-cloud-run.md). Sequential on purpose: the workers
# share the database and the counters it maintains. One failing command must not
# skip the rest of the tick, but the execution still reports the failure.

status=0
for command in "$@"; do
    if ! php /var/www/html/bin/console "$command"; then
        echo "console-batch: ${command} failed" >&2
        status=1
    fi
done

exit "$status"
