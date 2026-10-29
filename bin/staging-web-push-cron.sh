#!/bin/sh
set -eu
umask 077
STAGING_APP_PATH=/home/u577461158/domains/staging.tenantism.com/rentra-staging
cd "$STAGING_APP_PATH"
# Re-enable only proc_open for this CLI scheduler invocation; web PHP is unchanged.
exec /usr/bin/php \
  -d disable_functions=system,exec,shell_exec,passthru,mysql_list_dbs,ini_alter,dl,symlink,link,chgrp,leak,popen,apache_child_terminate,virtual,mb_send_mail \
  "$STAGING_APP_PATH/bin/staging-web-push-cron.php" \
  >> "$STAGING_APP_PATH/storage/logs/staging-web-push-cron.log" 2>&1
