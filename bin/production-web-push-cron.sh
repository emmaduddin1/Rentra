#!/bin/sh
set -eu
umask 077
PRODUCTION_APP_PATH=/home/u577461158/domains/tenantism.com/rentra
cd "$PRODUCTION_APP_PATH"
# Re-enable only proc_open for this CLI scheduler invocation; web PHP is unchanged.
exec /opt/alt/php85/usr/bin/php \
  -d disable_functions=system,exec,shell_exec,passthru,mysql_list_dbs,ini_alter,dl,symlink,link,chgrp,leak,popen,apache_child_terminate,virtual,mb_send_mail \
  "$PRODUCTION_APP_PATH/bin/production-web-push-cron.php" \
  >> "$PRODUCTION_APP_PATH/storage/logs/production-web-push-cron.log" 2>&1
