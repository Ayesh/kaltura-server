#!/bin/sh
. /etc/kaltura.d/system.ini

echo `date`

echo "get plays/views"
mysql -h$KAVA_DB_HOST -P$KAVA_DB_PORT -u$KAVA_DB_USER -p$KAVA_DB_PASS $KAVA_DB_NAME -BN -e 'select entry_id, UNIX_TIMESTAMP(last_played_at), plays, views, plays_30days, views_30days, plays_7days, views_7days, plays_1day, views_1day
from kava_plays_views' | php $BASE_DIR/app/alpha/scripts/kava/copyRecordingPlaysviewsToLive.php
# copyRecordingPlaysviewsToLive.php writes the dump to /tmp/playsviewsDump.txt
php $BASE_DIR/app/alpha/scripts/kava/updateEntryPlaysViews.php < /tmp/playsviewsDump.txt

