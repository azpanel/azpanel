<?php

namespace app\controller;

use app\controller\Tools;
use app\model\Ann;
use app\model\AutoRefresh;
use app\model\AzureRecycle;
use app\model\Config;
use app\model\LoginLog;
use app\model\Share;
use app\model\SshKey;
use app\model\User;
use phpseclib3\Crypt\RSA;
use think\facade\View;

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

    public function recycle()
    {
        $user_id = session('user_id');
        $accounts = AzureRecycle::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->paginate(30);

        $ranking = [
            'time' => AzureRecycle::where('user_id', $user_id)->max('life_cycle') ?? '0',
            'cost' => AzureRecycle::where('user_id', $user_id)->max('bill_charges') ?? '0',
        ];

        View::assign([
            'ranking' => $ranking,
            'accounts' => $accounts,
            'page' => $accounts->render(),
        ]);
        return View::fetch('../app/view/user/recycle.html');
    }

    public function shareList()
    {
        $records = Share::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->paginate(30);

        $page = $records->render();

        View::assign('page', $page);
        View::assign('records', $records);
        return View::fetch('../app/view/user/share.html');
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
        $user_id = session('user_id');
        $profile = User::find($user_id);
        $telegram = Config::group('telegram');
        $switch = Config::group('switch');
        $personalise = json_decode($profile->personalise, true);

        $refresh_setting = AutoRefresh::where('user_id', $user_id)->find();
        if ($refresh_setting === null) {
            $auto_refresh = new AutoRefresh();

            $auto_refresh->user_id = $user_id;
            $auto_refresh->rate = 24;
            $auto_refresh->push_swicth = 0;
            $auto_refresh->function_swicth = 0;
            $auto_refresh->created_at = time();
            $auto_refresh->updated_at = time();
            $auto_refresh->save();

            $refresh_setting = AutoRefresh::where('user_id', $user_id)->find();
        }

        $ssh_key = SshKey::where('user_id', session('user_id'))->find();

        View::assign('switch', $switch);
        View::assign('profile', $profile);
        View::assign('ssh_key', $ssh_key);
        View::assign('telegram', $telegram);
        View::assign('personalise', $personalise);
        View::assign('refresh_setting', $refresh_setting);
        View::assign('sizes', AzureList::sizes());
        View::assign('images', AzureList::images());
        View::assign('locations', AzureList::locations());
        View::assign('disk_sizes', AzureList::diskSizes());
        return View::fetch('../app/view/user/profile.html');
    }

    public function savePasswd()
    {
        $passwd = Tools::encryption(input('now_passwd/s'));
        $new_passwd = input('new_passwd/s');
        $again_passwd = input('again_passwd/s');

        if ($passwd === '' || $new_passwd === '' || $again_passwd === '') {
            return json(Tools::msg('0', '修改失败', '完成所有必要输入'));
        }

        if ($new_passwd !== $again_passwd) {
            return json(Tools::msg('0', '修改失败', '输入的新密码不一致'));
        }

        $user = User::find(session('user_id'));

        if ($user->passwd !== $passwd) {
            return json(Tools::msg('0', '修改失败', '当前密码不正确'));
        }

        $user->passwd = Tools::encryption($new_passwd);
        $user->save();

        return json(Tools::msg('1', '修改结果', '修改成功'));
    }

    public function saveNotify()
    {
        $user_id = session('user_id');
        $user_email = input('user_email/s');

        if (!Tools::emailCheck($user_email)) {
            return json(Tools::msg('0', '保存失败', '邮箱格式不规范'));
        }

        $user = User::find($user_id);
        $user->notify_email = input('user_email/s');
        $user->notify_tg = input('user_telegram/s');
        $user->notify_tgid = (int) input('user_telegram_id/s');
        $user->save();

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function savePersonalise()
    {
        $vm_user = input('vm_default_identity/s');
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
        $number = preg_match('@[0-9]@', $vm_passwd);

        if (!$uppercase || !$lowercase || !$number || strlen($vm_passwd) < 12 || strlen($vm_passwd) > 72) {
            return json(Tools::msg('0', '保存失败', '密码不符合规范，请阅读创建虚拟机页面的使用说明'));
        }

        $personalise = [
            'vm_size' => input('vm_size/s'),
            'vm_image' => input('vm_image/s'),
            'vm_location' => input('vm_location/s'),
            'vm_disk_size' => input('vm_disk_size/s'),
            'vm_default_script' => input('vm_default_script/s'),
            'vm_default_identity' => input('vm_default_identity/s'),
            'vm_default_credentials' => input('vm_default_credentials/s'),
        ];

        $user = User::find(session('user_id'));
        $user->personalise = json_encode($personalise);
        $user->save();

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function saveRefresh()
    {
        $user_id = session('user_id');
        $auto_refresh_rate = input('auto_refresh_rate/d');
        $auto_refresh_switch = input('auto_refresh_switch/d');
        $auto_refresh_telegram_push = input('auto_refresh_telegram_push/d');

        $user = User::find($user_id);
        if ($auto_refresh_telegram_push === 1 && $auto_refresh_switch === 1) {
            if (!isset($user->notify_tgid)) {
                return json(Tools::msg('0', '保存失败', '请先设置 Telegram 推送接收账户'));
            }
        }

        $refresh_setting = AutoRefresh::where('user_id', $user_id)->find();
        $refresh_setting->rate = $auto_refresh_rate;
        $refresh_setting->push_swicth = $auto_refresh_telegram_push;
        $refresh_setting->function_swicth = $auto_refresh_switch;
        $refresh_setting->save();

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function createSshKey()
    {
        $ssh_key = RSA::createKey();
        $name = date('Ymd') . '.pem';
        $public = $ssh_key->getPublicKey()->toString('OpenSSH');
        $private = $ssh_key->toString('OpenSSH');

        $key = new SshKey();
        $key->name = $name;
        $key->user_id = session('user_id');
        $key->public_key = $public;
        $key->created_at = time();
        $key->save();

        return download($private, $name, true);
    }

    public function resetSshKey()
    {
        SshKey::where('user_id', session('user_id'))->delete();
        return json(Tools::msg('1', '重置成功', '请点击按钮重新生成'));
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
