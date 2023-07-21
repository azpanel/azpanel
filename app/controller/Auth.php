<?php

namespace app\controller;

use app\BaseController;
use app\controller\AzureList;
use app\controller\Notify;
use app\controller\Tools;
use app\model\Config;
use app\model\LoginLog;
use app\model\User;
use app\model\Verify;
use think\facade\Request;
use think\facade\View;
use think\helper\Str;

class Auth extends BaseController
{
    public function index()
    {
        if ((int) session('is_login') === 1) {
            return redirect('/user')->send();
        }

        View::assign('verify', Config::group('verification_code'));
        return View::fetch('../app/view/auth/login.html');
    }

    public function login()
    {
        $code = input('code/s');
        $email = input('email/s');
        $password = Tools::encryption(input('password/s'));
        $ip_address = Tools::getClientIp();

        if ($email === '' || $password === '') {
            $data = ['status' => '0', 'title' => '登录失败', 'content' => '邮箱或密码不能为空'];
            return json($data);
        }

        if (!Tools::emailCheck($email)) {
            return json(Tools::msg('0', '登录失败', '邮箱不规范'));
        }

        if (Config::obtain('login_verification_code')) {
            if (Config::obtain('captcha_provider') === 'hcaptcha') {
                $code = input('hcaptcha_result/s');
                if ($code === '') {
                    return json(Tools::msg('0', '登录失败', '请完成验证码'));
                }
                if (!Tools::verifyHcaptcha($code)) {
                    return json(Tools::msg('0', '登录失败', '验证码错误'));
                }
            }
            if (!captcha_check($code) && Config::obtain('captcha_provider') === 'think-captcha') {
                return json(Tools::msg('0', '登录失败', '验证码错误'));
            }
        }

        $log = new LoginLog();
        $log->email = $email;
        $log->ip = $ip_address;
        $log->ip_info = Tools::ipInfo($ip_address);
        $log->created_at = time();
        $log->ua = Request::header('user-agent');

        $user = User::where('email', $email)->find();
        if ($user === null) {
            $log->status = 0;
            $log->info = 'invalid.user';
            $log->save();

            $data = ['status' => '0', 'title' => '登录失败', 'content' => '用户不存在'];
            return json($data);
        }

        if ($password !== $user->passwd) {
            $log->status = 0;
            $log->info = 'passwd.error';
            $log->save();

            $data = ['status' => '0', 'title' => '登录失败', 'content' => '密码不正确'];
            return json($data);
        }

        if ($user->status === 0) {
            $log->status = 0;
            $log->info = 'disabled.status';
            $log->save();

            $data = ['status' => '0', 'title' => '登录失败', 'content' => '账户被停用，请联系管理员'];
            return json($data);
        }

        session('is_login', 1);
        session('user_id', $user->id);

        if ($user->is_admin === 1) {
            session('is_admin', 1);
        }

        // 记录
        $log->status = 1;
        $log->info = 'success';
        $log->save();

        $data = ['status' => '1', 'title' => '登录成功', 'content' => '欢迎回来'];
        return json($data);
    }

    public function logout()
    {
        session(null);

        $data = ['status' => '1', 'title' => '登出成功', 'content' => '将返回登录页'];
        return json($data);
    }

    public function registerIndex()
    {
        View::assign('register', Config::group('register'));
        View::assign('verify', Config::group('verification_code'));
        return View::fetch('../app/view/auth/register.html');
    }

    public function registerCode()
    {
        $ip = Tools::getClientIp();
        $email = input('email/s');

        if (!Tools::emailCheck($email)) {
            return json(Tools::msg('0', '注册失败', '邮箱不规范'));
        }

        if (Config::obtain('reg_email_veriy') === false) {
            return json(Tools::msg('0', '发送失败', '注册无需验证邮箱'));
        }

        $exist = Verify::where('email', $email)
            ->order('id', 'desc')
            ->find();

        if ($exist !== null && (time() - $exist->created_at) < 60) {
            return json(Tools::msg('0', '发送失败', '两次发送时间间隔小于 60 秒'));
        }

        $exist = User::where('email', $email)->find();
        if ($exist !== null) {
            return json(Tools::msg('0', '注册失败', '此邮箱已注册'));
        }

        $count = Verify::where('email', $email)
            ->where('created_at', '>', time() - 86400)
            ->count();

        if ($count > 5) {
            return json(Tools::msg('0', '发送失败', '24 小时内一个邮箱只能请求 5 次验证码'));
        }

        $count = Verify::where('email', $email)
            ->where('created_at', '>', time() - 86400)
            ->where('ip', $ip)
            ->count();

        if ($count > 10) {
            return json(Tools::msg('0', '发送失败', '24 小时内一个 IP 只能请求 10 次验证码'));
        }

        $code = Str::random($length = 8);
        $code = Str::lower($code);

        $verify = new Verify();
        $verify->email = $email;
        $verify->type = 'register';
        $verify->code = $code;
        $verify->ip = $ip;
        $verify->created_at = time();
        $verify->expired_at = time() + 600;
        $verify->save();

        Notify::email($email, '注册验证码', '十分钟内有效：' . $code);

        return json(Tools::msg('1', '发送成功', '请检查收件箱或垃圾箱'));
    }

    public function publicRegister()
    {
        $code = input('code/s');
        $email = input('email/s');
        $passwd = input('passwd/s');
        $repeat_passwd = input('repeat_passwd/s');

        if (!Tools::emailCheck($email)) {
            return json(Tools::msg('0', '注册失败', '邮箱不规范'));
        }

        if (Config::obtain('registration_verification_code')) {
            if (Config::obtain('captcha_provider') === 'hcaptcha') {
                $code = input('hcaptcha_result/s');
                if ($code === '') {
                    return json(Tools::msg('0', '注册失败', '请完成图像验证码填写'));
                }
                if (!Tools::verifyHcaptcha($code)) {
                    return json(Tools::msg('0', '注册失败', '图像验证码错误'));
                }
            }
            if (!captcha_check($code) && Config::obtain('captcha_provider') === 'think-captcha') {
                return json(Tools::msg('0', '注册失败', '图像验证码错误'));
            }
        }

        $exist = User::where('email', $email)->find();
        if ($exist !== null) {
            return json(Tools::msg('0', '注册失败', '此邮箱已注册'));
        }

        if ($passwd === '' || $repeat_passwd === '') {
            return json(Tools::msg('0', '注册失败', '请设置密码'));
        }

        if ($passwd !== $repeat_passwd) {
            return json(Tools::msg('0', '注册失败', '两次输入的密码不符'));
        }

        if (Config::obtain('reg_email_veriy') === true) {
            $verify_code = input('verify_code/s');

            $verify = Verify::where('email', $email)
                ->order('id', 'desc')
                ->find();

            if ($verify === null || (string) $verify->code !== (string) $verify_code) {
                return json(Tools::msg('0', '注册失败', '验证码不相符'));
            }

            if (time() > $verify->expired_at) {
                return json(Tools::msg('0', '注册失败', '验证码已过期'));
            }

            $verify->result = 1;
            $verify->save();
        }

        $user = new User();
        $user->email = $email;
        $user->passwd = Tools::encryption($passwd);
        $user->status = 1;
        $user->personalise = AzureList::defaultPersonalise();
        $user->created_at = time();
        $user->updated_at = time();
        $user->save();

        return json(Tools::msg('1', '注册结果', '注册成功'));
    }

    public function forgetIndex()
    {
        return View::fetch('../app/view/auth/forget.html');
    }

    public function forgetCode()
    {
        $ip = Tools::getClientIp();
        $email = input('email/s');

        if (!Config::obtain('reg_email_veriy')) {
            return json(Tools::msg('0', '发送失败', '未启用邮件发信功能，请联系管理员重置密码'));
        }

        if (!Tools::emailCheck($email)) {
            return json(Tools::msg('0', '发送失败', '邮箱不规范'));
        }

        $exist = Verify::where('email', $email)
            ->order('id', 'desc')
            ->find();

        if ($exist !== null && (time() - $exist->created_at) < 60) {
            return json(Tools::msg('0', '发送失败', '两次发送时间间隔小于 60 秒'));
        }

        $exist = User::where('email', $email)->find();
        if ($exist === null) {
            return json(Tools::msg('0', '发送失败', '此邮箱未注册'));
        }

        $count = Verify::where('email', $email)
            ->where('created_at', '>', time() - 86400)
            ->count();

        if ($count > 5) {
            return json(Tools::msg('0', '发送失败', '24 小时内一个邮箱只能请求 5 次验证码'));
        }

        $count = Verify::where('email', $email)
            ->where('created_at', '>', time() - 86400)
            ->where('ip', $ip)
            ->count();

        if ($count > 10) {
            return json(Tools::msg('0', '发送失败', '24 小时内一个 IP 只能请求 10 次验证码'));
        }

        $code = Str::random($length = 8);
        $code = Str::lower($code);

        $verify = new Verify();
        $verify->email = $email;
        $verify->type = 'forget';
        $verify->code = $code;
        $verify->ip = $ip;
        $verify->created_at = time();
        $verify->expired_at = time() + 600;
        $verify->save();

        Notify::email($email, '重置密码验证码', '十分钟内有效：' . $code);

        return json(Tools::msg('1', '发送成功', '请检查收件箱或垃圾箱'));
    }

    public function resetPassword()
    {
        $email = input('email/s');
        $passwd = input('passwd/s');
        $verify_code = input('verify_code/s');
        $repeat_passwd = input('repeat_passwd/s');

        if (!Tools::emailCheck($email)) {
            return json(Tools::msg('0', '重置失败', '邮箱不规范'));
        }

        $exist = User::where('email', $email)->find();
        if ($exist === null) {
            return json(Tools::msg('0', '重置失败', '此邮箱未注册'));
        }

        if ($passwd !== $repeat_passwd) {
            return json(Tools::msg('0', '重置失败', '两次输入的密码不符'));
        }

        $verify = Verify::where('email', $email)
            ->order('id', 'desc')
            ->find();

        if ((string) $verify->code !== (string) $verify_code) {
            return json(Tools::msg('0', '重置失败', '验证码不相符'));
        }

        if (time() > $verify->expired_at) {
            return json(Tools::msg('0', '重置失败', '验证码已过期'));
        }

        $verify->result = 1;
        $verify->save();

        $user = User::where('email', $email)->find();
        $user->passwd = Tools::encryption($passwd);
        $user->updated_at = time();
        $user->save();

        return json(Tools::msg('1', '重置结果', '重置成功'));
    }
}
