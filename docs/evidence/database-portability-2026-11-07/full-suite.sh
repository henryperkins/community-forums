#!/usr/bin/env bash
set -euo pipefail

export MAIL_DRIVER=array

# A force-reset belongs only to this preflight. Race-test children load the
# bootstrap again and must reuse their parent's schema and committed fixtures.
RB_TEST_FRESH=1 php -r 'require "tests/bootstrap.php";'
unset RB_TEST_FRESH
composer test -- --log-events-text "$1" --display-deprecations
