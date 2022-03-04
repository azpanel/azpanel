<?php
namespace app\controller;

use app\BaseController;

class UserBase extends BaseController
{
    public function initialize()
    {
        if (session('is_login') != '1') {
            return redirect('/login')->send();
        }
    }
}
