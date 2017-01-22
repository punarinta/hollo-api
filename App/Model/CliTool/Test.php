<?php

namespace App\Model\CliTool;

use App\Model\Inbox\Imap;
use \App\Service\Chat as ChatSvc;
use App\Service\File;
use \App\Service\User as UserSvc;
use \App\Service\Notify as NotifySvc;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function inbox($userId = 2)
    {
        $imap = new Imap($userId); // 11298 - attachment, 11216 - polish symbols

        print_r($imap->getMessages(['ts_after' => strtotime('2016-10-14')]));

    //    print_r($imap->getMessage(11077));

    //    $imap = new Gmail($userId);
    //    print_r($imap->getMessage('157c395527a42e95'));

        return '';
    }

    public function notify()
    {
        $id = UserSvc::findOne(['email' => 'vladimir.g.osipov@gmail.com'])->_id;

        $res = NotifySvc::auto([$id], ['cmd' => 'sys:ping'], ['title' => 'You have new message', 'body' => 'Testing 🎂']);

        print_r($res);
    }

    public function mongo()
    {
        $users = ChatSvc::findByEmails(['felix.r.lange@gmail.com', 'fredrik.engblom@gmail.com']);
        print_r($users);
    }

    public function thumb($filePath)
    {
        $tempFileName = tempnam('data/temp', 'THUMB-');
        File::createGdThumbnail($filePath, $tempFileName);
        rename($tempFileName, $tempFileName . '.jpg');
    }

    public function imagick($filePath)
    {
        try
        {
            $tempFileName = tempnam('data/temp', 'THUMB-');
            $im = new \imagick($filePath . '[0]');
            $im->setImageFormat('png');
            $im->trimImage(0);
            file_put_contents($tempFileName, $im);
            rename($tempFileName, $tempFileName . '.png');
        }
        catch (\Exception $e)
        {
            return "Error: {$e->getMessage()}\n\n";
        }

        return "Done!\n\n";
    }
}
