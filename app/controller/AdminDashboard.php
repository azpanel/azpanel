<?php

namespace app\controller;

use think\facade\Db;
use think\facade\View;

class AdminDashboard extends AdminBase
{
    public function index()
    {
        $info = [
            //服务器系统
            'server_os' => PHP_OS,
            //服务器ip
            'server_ip' => gethostbyname($_SERVER['SERVER_NAME']),
            //磁盘空间
            'disk_space' => round(disk_free_space('.') / 1e9, 2) . ' GB',
            //php版本
            'php_version' => PHP_VERSION,
            //运行内存限制
            'memory_limit' => ini_get('memory_limit'),
            //ThinkPHP版本
            'think_version' => app()->version(),
            //运行模式
            'php_sapi_name' => PHP_SAPI,
            //mysql版本
            'db_version' => Db::query('select VERSION() as db_version')[0]['db_version'],
            //php时区
            'timezone' => date_default_timezone_get(),
        ];

        View::assign('info', $info);
        return View::fetch('../app/view/admin/index.html');
    }
}
