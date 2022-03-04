<?php
namespace app\controller;

use think\facade\View;
use app\controller\Tools;
use app\model\User;
use app\model\Ann;
use app\model\Config;
use app\model\LoginLog;

class UserDashboard extends UserBase
{
    public function index()
    {
        $anns = Ann::where('status', '1')
        ->order('id', 'desc')
        ->select();

        View::assign('anns', $anns);
        return View::fetch('../app/view/user/index.html');
    }

    public function loginLog()
    {
        $user = User::find(session('user_id'));
        
        $logs = LoginLog::where('email', $user->email)
        ->order('id', 'desc')
        ->paginate(30);

        $page = $logs->render();
        
        View::assign('page', $page);
        View::assign('logs', $logs);
        return View::fetch('../app/view/user/loginlog.html');
    }

    public function profile()
    {
        $profile     = User::find(session('user_id'));
        $telegram    = Config::class('telegram');
        $switch      = Config::class('switch');
        $personalise = json_decode($profile->personalise, true);
        $disk_sizes   = ['32', '64', '128', '256', '512', '1024'];
        
        View::assign('locations', AzureList::locations());
        View::assign('images', AzureList::images());
        View::assign('sizes', AzureList::sizes());
        View::assign('personalise', $personalise);
        View::assign('disk_sizes', $disk_sizes);
        View::assign('telegram', $telegram);
        View::assign('profile', $profile);
        View::assign('switch', $switch);
        return View::fetch('../app/view/user/profile.html');
    }
    
    public function savePasswd()
    {
        $passwd       = Tools::encryption(input('now_passwd/s'));
        $new_passwd   = input('new_passwd/s');
        $again_passwd = input('again_passwd/s');

        if ($passwd == '' || $new_passwd == '' || $again_passwd == '') {
            return json(Tools::msg('0', '修改失败', '完成所有必要输入'));
        }

        if ($new_passwd != $again_passwd) {
            return json(Tools::msg('0', '修改失败', '输入的新密码不一致'));
        }

        $user = User::find(session('user_id'));

        if ($user->passwd != $passwd) {
            return json(Tools::msg('0', '修改失败', '当前密码不正确'));
        }

        $user->passwd = Tools::encryption($new_passwd);
        $user->save();

        return json(Tools::msg('1', '修改结果', '修改成功'));
    }

    public function saveNotify()
    {
        if (!Tools::emailCheck(input('user_email/s'))) {
            return json(Tools::msg('0', '保存失败', '邮箱格式不规范'));
        }
        
        $user = User::find(session('user_id'));
        $user->notify_email = input('user_email/s');
        $user->notify_tg    = input('user_telegram/s');
        $user->notify_tgid  = input('user_telegram_id');
        $user->save();

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function savePersonalise()
    {
        $vm_user   = input('vm_default_identity/s');
        $vm_passwd = input('vm_default_credentials/s');
        $prohibit_user = ['root', 'admin', 'centos', 'debian', 'ubuntu'];
        
        if (in_array($vm_user, $prohibit_user)) {
            return json(Tools::msg('0', '保存失败', '不能使用常见用户名'));
        }
        
        if (!preg_match('/^[a-zA-Z0-9]+$/', $vm_user)) {
            return json(Tools::msg('0', '保存失败', '用户名只允许使用大小写字母与数字的组合'));
        }

        $uppercase = preg_match('@[A-Z]@', $vm_passwd);
        $lowercase = preg_match('@[a-z]@', $vm_passwd);
        $number    = preg_match('@[0-9]@', $vm_passwd);

        if (!$uppercase || !$lowercase || !$number || strlen($vm_passwd) < 12 || strlen($vm_passwd) > 72) {
            return json(Tools::msg('0', '保存失败', '密码不符合规范，请阅读创建虚拟机页面的使用说明'));
        }
        
        $personalise = [
            'vm_size'                => input('vm_size/s'),
            'vm_image'               => input('vm_image/s'),
            'vm_location'            => input('vm_location/s'),
            'vm_disk_size'           => input('vm_disk_size/s'),
            'vm_default_script'      => input('vm_default_script/s'),
            'vm_default_identity'    => input('vm_default_identity/s'),
            'vm_default_credentials' => input('vm_default_credentials/s'),
        ];

        $user = User::find(session('user_id'));
        $user->personalise = json_encode($personalise);
        $user->save();

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function license()
    {
        return View::fetch('../app/view/user/license.html');
    }

    public function docs()
    {
        return View::fetch('../app/view/user/docs.html');
    }
}
