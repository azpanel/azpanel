<?php

namespace app\controller;

use think\facade\View;
use app\model\Proxy;
use app\model\Azure;
use app\controller\Tools;

class UserAzureProxy extends UserBase
{
    public function index()
    {
        $proxies = Proxy::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->select();

        View::assign('proxies', $proxies);
        View::assign('count', $proxies->count());
        return View::fetch('../app/view/user/azure/proxy/index.html');
    }

    public function create()
    {
        return View::fetch('../app/view/user/azure/proxy/create.html');
    }

    public function save()
    {
        $proxy_name         = input('proxy_name/s');
        $proxy_proxy        = input('proxy_proxy/s');

        if (empty($proxy_name)) {
            return json(Tools::msg('0', '创建失败', '须设置规则名称'));
        }

        $proxy = new Proxy;
        $proxy->user_id      = session('user_id');
        $proxy->name         = $proxy_name;
        $proxy->proxy        = $proxy_proxy;
        $proxy->created_at   = time();
        $proxy->updated_at   = time();
        $proxy->save();

        return json(Tools::msg('1', '创建结果', '创建成功'));
    }

    public function edit($id)
    {
        $proxy = Proxy::find($id);
        if ($proxy == null || $proxy->user_id != session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        View::assign('proxy', $proxy);
        return View::fetch('../app/view/user/azure/proxy/edit.html');
    }

    public function update($id)
    {
        $proxy_name         = input('proxy_name/s');
        $proxy_proxy        = input('proxy_proxy/s');

        if (empty($proxy_name)) {
            return json(Tools::msg('0', '更新失败', '须设置代理名称'));
        }
        if (empty($proxy_proxy)) {
            return json(Tools::msg('0', '更新失败', '须设置代理'));
        }

        $rule = Proxy::where('user_id', session('user_id'))->find($id);
        $rule->name         = $proxy_name;
        $rule->proxy        = $proxy_proxy;
        $rule->updated_at   = time();
        $rule->save();

        return json(Tools::msg('1', '更新结果', '更新成功'));
    }

    public function delete($id)
    {
        $azs = Azure::where('user_id', session('user_id'))
            ->where('proxy', $id)
            ->select();

        foreach ($azs as $az) {
            $az->proxy = 0;
            $az->save();
        }

        Proxy::where('user_id', session('user_id'))
            ->where('id', $id)
            ->delete();

        return json(Tools::msg('1', '删除结果', '删除成功'));
    }
}
