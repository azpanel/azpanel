<?php

namespace app\controller;

use app\BaseController;

class UserBase extends BaseController
{
    public function initialize()
    {
        $is_login = (int) session('is_login');
        if ($is_login !== 1) {
            return redirect('/login')->send();
        }
    }
}
