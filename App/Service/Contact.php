<?php

namespace App\Service;

/**
 * The class is still necessary for Contact abstraction to work with Context.IO
 *
 * Class Contact
 * @package App\Service
 */
class Contact
{
    /**
     * Check if the contact is muted by default or not
     *
     * @param $email
     * @return bool
     */
    public static function isMuted($email)
    {
        $email = explode('@', $email);

        if (in_array($email[0],
        [
            'account',
            'admin',
            'automailer',
            'bekraftelse',
            'billing',
            'booking',
            'career',
            'contact',
            'demo',
            'delivery',
            'discover',
            'donate',
            'email',
            'event',
            'forum',
            'hello',
            'help',
            'info',
            'inform',
            'invite',
            'mail',
            'marketing',
            'medlem',
            'member',
            'messages',
            'nobody',
            'online',
            'post',
            'postmaster',
            'reklama',
            'robot',
            'student',
            'subscription',
            'team',
            'webmaster',
            'weekly',
            'welcome',
        ])
            || strpos($email[0], 'customer') !== false
            || strpos($email[0], 'feedback') !== false
            || strpos($email[0], 'mailer') !== false
            || strpos($email[0], 'message') !== false
            || strpos($email[0], 'news') !== false
            || strpos($email[0], 'notif') !== false
            || strpos($email[0], 'nyhet') !== false
            || strpos($email[0], 'regist') !== false
            || strpos($email[0], 'reply') !== false
            || strpos($email[0], 'sales') !== false
            || strpos($email[0], 'service') !== false
            || strpos($email[0], 'support') !== false
            || strpos($email[0], 'survey') !== false
            || strpos($email[0], 'update') !== false
        )
        {
            return true;
        }
        elseif (count($email) === 2)
        {
            // lookup the email in the spam database and force 'muted' to 1 if found

            $rows = [];
            foreach (\DB::query('muted', ['domain' => $email[1]]) as $row)
            {
                unset ($row->_id);
                $rows[] = $row;
            }

            if (count($rows))
            {
                if (!@$rows[0]->user)
                {
                    return true;
                }
                else
                {
                    foreach ($rows as $row)
                    {
                        if ($row->user == $email[0])
                        {
                            return true;
                        }
                    }
                }
            }
        }

        // emails consisting from numbers only are probably not what you want
        if (intval($email[0]) > 0) return true;

        return false;
    }
}
