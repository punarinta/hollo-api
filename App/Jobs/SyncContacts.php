<?php

namespace App\Jobs;

use MongoDB\BSON\ObjectID;
use \App\Service\User as UserSvc;
use \App\Service\Message as MessageSvc;
use \App\Service\MailService as MailServiceSvc;

/**
 * This is basically called only one time -- when user is connected to Context
 *
 * Class SyncContacts
 * @package App\Jobs
 */
class SyncContacts extends Generic
{
    public function testSetup()
    {
        $user = UserSvc::findOne(['email' => 'hollo.email@gmail.com']);

        $this->args = array
        (
            'user_id' => $user->_id,
        );
    }

    public function perform()
    {
        if (!$user = UserSvc::findOne(['_id' => new ObjectID($this->args['user_id'])]))
        {
            echo "No user found (ID = {$this->args['user_id']}).\n";
            return false;
        }

        if (!$user->roles)
        {
            echo "No user role for ID = {$user->_id}. Is this user real?\n";
            return false;
        }

        $gmailSvcId = MailServiceSvc::findOne(['name' => 'Gmail'])->_id;

        if (@$user->settings->svc == $gmailSvcId)
        {
            // subscribe for future updates
            UserSvc::subscribeToGmail($user);
        }

        // fetch all the messages
        MessageSvc::syncAllByUserId($user, false);

        if (@$user->settings->svc == $gmailSvcId)
        {
            // TODO: move avatar checking logic into updateAvatars() after avatar server is up

            // fetch avatars
            UserSvc::updateAvatars($user);
        }

        return true;
    }
}
