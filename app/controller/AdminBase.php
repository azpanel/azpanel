<?php

namespace app\controller;

use app\BaseController;

class AdminBase extends BaseController
{
    public function initialize()
    {
        if ((int) session('is_admin') !== 1) {
            if ((int) session('is_login') !== 1) {
                return redirect('/login')->send();
            }
            return redirect('/user')->send();
        }
    }
}
