#!/bin/sh
#this script is always launched as non-root
set -e

touch /var/log/cron.log

FALLBACK_CONFIG="* * * * * /bin/echo '.' >> /var/log/cron.log 2>&1"
CRON_CONFIG=${CRON_CONFIG:-"$FALLBACK_CONFIG"}
echo "${CRON_CONFIG}" > /tmp/crontab && cat /tmp/crontab
crontab /tmp/crontab

crond
tail -f /var/log/cron.log
