<?php

namespace App\Controller;

class Test extends Generic
{
    static public function hello()
    {
        return \Sys::svc('Communicator')->test();
    }
}