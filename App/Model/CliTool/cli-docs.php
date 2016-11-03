<?php

echo "admin sync [USER_ID] (WITH_MUTED) (ALL)                   Sync all account's emails.\n";
echo "admin syncMessage [USER_ID] [MSG_EID] (VERBOSE)           Sync specific email.\n";
echo "admin hashpass [PASSWORD]                                 Creates a hash for a password.\n";
echo "fetch file [MSG_ID] [FILE_OFFSET]                         Get file contents.\n";
echo "fetch message [USER_ID] [MSG_EID] (FULL)                  Get a message.\n";
echo "cron removeOldMessages                                    Purge old messages from DB.\n";
echo "cron refreshGmailSubscription                             Refresh Gmail webhooks.\n";
echo "cron updateAvatars                                        Resync avatars for all the users.\n";
