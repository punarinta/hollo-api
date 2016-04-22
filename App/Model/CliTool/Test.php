<?php

namespace App\Model\CliTool;

/**
 * Class Test
 * @package App\Model\CliTool
 */
class Test
{
    public function smtp()
    {
        \Sys::svc('Smtp')->setupThread(1, 1);
        \Sys::svc('Smtp')->send([['email'=>'cheaterx@yandex.ru', 'name'=>'Test Guy']], 'hollo, world!' . uniqid());
        return "\n";
    }
}
