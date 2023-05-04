<?php

namespace app\controller;

use app\controller\Tools;
use app\model\AzureServer;
use app\model\ControlLog;
use app\model\ControlRule;
use think\facade\View;

class UserAzureServerRule extends UserBase
{
    public function index()
    {
        $rules = ControlRule::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->select();

        View::assign('rules', $rules);
        View::assign('count', $rules->count());
        return View::fetch('../app/view/user/azure/rule/index.html');
    }

    public function create()
    {
        return View::fetch('../app/view/user/azure/rule/create.html');
    }

    public function read($id)
    {
        $servers = AzureServer::where('user_id', session('user_id'))
            ->where('rule', $id)
            ->select();

        View::assign('count', $servers->count());
        View::assign('servers', $servers);
        return View::fetch('../app/view/user/azure/rule/read.html');
    }

    public function save()
    {
        $rule_name = input('rule_name/s');
        $rule_index = input('rule_index/s');
        $rule_time = (int) input('rule_time/s');
        $rule_limit = (int) input('rule_limit/s');
        $rule_switch = (int) input('rule_switch/s');
        $rule_interval = (int) input('rule_interval/s');
        $rule_execute_push = (int) input('rule_execute_push/s');
        $rule_recover_push = (int) input('rule_recover_push/s');

        if ($rule_name === '') {
            return json(Tools::msg('0', '创建失败', '须设置规则名称'));
        }

        $rule = new ControlRule();
        $rule->user_id = session('user_id');
        $rule->name = $rule_name;
        $rule->index = $rule_index;
        $rule->time = $rule_time;
        $rule->limit = $rule_limit;
        $rule->switch = $rule_switch;
        $rule->interval = $rule_interval;
        $rule->created_at = time();
        $rule->updated_at = time();
        $rule->execute_push = $rule_execute_push;
        $rule->recover_push = $rule_recover_push;
        $rule->save();

        return json(Tools::msg('1', '创建结果', '创建成功'));
    }

    public function edit($id)
    {
        $rule = ControlRule::find($id);
        if ($rule === null || $rule->user_id !== (int) session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        View::assign('rule', $rule);
        return View::fetch('../app/view/user/azure/rule/edit.html');
    }

    public function update($id)
    {
        $rule_name = input('rule_name/s');
        $rule_index = input('rule_index/s');
        $rule_time = (int) input('rule_time/s');
        $rule_limit = (int) input('rule_limit/s');
        $rule_switch = (int) input('rule_switch/s');
        $rule_interval = (int) input('rule_interval/s');
        $rule_execute_push = (int) input('rule_execute_push/s');
        $rule_recover_push = (int) input('rule_recover_push/s');

        if ($rule_name === '') {
            return json(Tools::msg('0', '更新失败', '须设置规则名称'));
        }

        $rule = ControlRule::where('user_id', session('user_id'))->find($id);
        $rule->name = $rule_name;
        $rule->index = $rule_index;
        $rule->time = $rule_time;
        $rule->limit = $rule_limit;
        $rule->switch = $rule_switch;
        $rule->interval = $rule_interval;
        $rule->updated_at = time();
        $rule->execute_push = $rule_execute_push;
        $rule->recover_push = $rule_recover_push;
        $rule->save();

        return json(Tools::msg('1', '更新结果', '更新成功'));
    }

    public function delete($id)
    {
        ControlRule::where('user_id', session('user_id'))
            ->where('id', $id)
            ->delete();

        $servers = AzureServer::where('user_id', session('user_id'))
            ->where('rule', $id)
            ->select();

        foreach ($servers as $server) {
            $server->rule = 0;
            $server->save();
        }

        return json(Tools::msg('1', '删除结果', '删除成功'));
    }

    public function log()
    {
        $logs = ControlLog::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->paginate(30);

        $page = $logs->render();

        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/user/azure/rule/traffic.html');
    }
}
