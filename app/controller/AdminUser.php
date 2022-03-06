<?php
namespace app\controller;

use think\facade\View;
use think\facade\Request;
use think\helper\Str;
use app\controller\Tools;
use app\controller\Notify;
use app\controller\AzureList;
use app\model\User;
use app\model\Azure;
use app\model\Config;
use app\model\AzureServer;

class AdminUser extends AdminBase
{
    public function index()
    {
        $users = User::paginate(10);
        
        View::assign('users', $users);
        View::assign('page', $users->render());
        return View::fetch('../app/view/admin/user/index.html');
    }

    public function create()
    {
        return View::fetch('../app/view/admin/user/create.html');
    }

    public function update($id)
    {
        $user = User::find($id);
        $user->remark     = (input('remark') == '') ? null : input('remark');
        $user->email      = input('email');
        $user->is_admin   = input('admin');
        $user->status     = input('status');
        $user->updated_at = time();
        
        if (input('passwd') != '') {
            $user->passwd = Tools::encryption(input('passwd'));
        }
        
        $user->save();
        return json(Tools::msg('1', '修改结果', '修改成功'));
    }

    public function edit($id)
    {
        $user = User::find($id);

        View::assign('user', $user);
        return View::fetch('../app/view/admin/user/edit.html');
    }

    public function save()
    {
        $exist = User::where('email', input('email'))->find();
        if ($exist != null) {
        	return json(Tools::msg('0', '添加失败', '此邮箱已注册'));
        }
        
        $user = new User;
        $user->email      = input('email');
        $user->is_admin   = input('admin');
        $user->status     = 1;
        $user->created_at = time();
        $user->updated_at = time();
        
        (input('passwd') == '') ? $passwd = Str::random($length = 16) : $passwd = input('passwd');
        (input('remark') == '') ? $remark = null : $remark = input('remark');

        $user->passwd      = Tools::encryption($passwd);
        $user->personalise = AzureList::defaultPersonalise();
        $user->remark      = $remark;
        
        if (Config::obtain('email_notify')) {
            $text = '欢迎使用 Azure Panel'
            . '<br/>登录账户：' . input('email')
            . '<br/>登录密码：' . $passwd
            . '<br/>登录地址：' . Request::domain();
            Notify::email(input('email'), '登录信息', $text);
        }
        
        $user->save();
        return json(Tools::msg('1', '添加结果', '添加成功'));
    }

    public function remark($id)
    {
        $remark = input('remark');
        if ($remark == '') {
            return json(Tools::msg('0', '修改结果', '备注不能为空'));
        }

        $user = User::find($id);
        $user->remark = $remark;
        $user->updated_at = time();
        $user->save();

        return json(Tools::msg('1', '修改结果', '修改成功'));
    }

    public function delete($id)
    {
        if ($id == session('user_id')) {
        	return json(Tools::msg('0', '删除失败', '不能删除当前登录账户'));
        }
        
        User::destroy($id);
        Azure::where('user_id', $id)->delete();
        AzureServer::where('user_id', $id)->delete();

        return json(Tools::msg('1', '删除结果', '已删除此用户'));
    }

    public function userAssets($id)
    {
        $accounts = Azure::where('user_id', $id)->select();
        $servers = AzureServer::where('user_id', $id)->select();

        View::assign('server_count', 0);
        View::assign('account_count', 0);
        View::assign('servers', $servers);
        View::assign('accounts', $accounts);
        return View::fetch('../app/view/admin/user/assets.html');
    }
}
