<?php

namespace app\controller;

use app\model\AzureServerResize;
use app\model\ControlLog;
use app\model\LoginLog;
use app\model\Task;
use app\model\Verify;
use think\facade\View;

class AdminLog extends AdminBase
{
    public function login()
    {
        $logs = LoginLog::order('id', 'desc')->paginate(30);
        $page = $logs->render();

        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/admin/log/login.html');
    }

    public function verify()
    {
        $logs = Verify::order('id', 'desc')->paginate(30);
        $page = $logs->render();

        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/admin/log/verify.html');
    }

    public function resize()
    {
        $logs = AzureServerResize::order('id', 'desc')->paginate(30);
        $page = $logs->render();

        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/admin/log/resize.html');
    }

    public function traffic()
    {
        $logs = ControlLog::order('id', 'desc')->paginate(30);
        $page = $logs->render();

        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/admin/log/traffic.html');
    }

    public function task()
    {
        $logs = Task::order('id', 'desc')->paginate(30);
        $page = $logs->render();

        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/admin/log/task.html');
    }

    public function taskDetails($id)
    {
        $log = Task::find($id);
        $total = json_decode($log->total, true);
        $error = isset($log->error) ? json_decode($log->error) : null;
        $params = json_decode($log->params);
        $ignore_check = $params->account->check ?? null;
        $error_code = $error->error->code ?? null;
        $error = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $params = json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        View::assign([
            'log' => $log,
            'count' => '0',
            'total' => $total,
            'error' => $error,
            'params' => $params,
            'error_code' => $error_code,
            'ignore_check' => $ignore_check,
        ]);
        return View::fetch('../app/view/admin/log/task_details.html');
    }
}
