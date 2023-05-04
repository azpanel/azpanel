<?php

namespace app\controller;

use app\controller\Notify;
use app\controller\Tools;
use app\model\Config;
use think\facade\View;

class AdminSetting extends AdminBase
{
    public function baseIndex()
    {
        View::assign('switch', Config::group('switch'));
        View::assign('register', Config::group('register'));
        View::assign('verify', Config::group('verification_code'));
        return View::fetch('../app/view/admin/setting/index.html');
    }

    public function baseSave()
    {
        $class = input('class/s');

        if ($class === 'notify') {
            $list = ['email_notify', 'telegram_notify'];
        } elseif ($class === 'register') {
            $list = ['allow_public_reg', 'reg_email_veriy'];
        } elseif ($class === 'verify') {
            $list = ['captcha_provider', 'registration_verification_code', 'login_verification_code', 'reset_password_verification_code', 'create_virtual_machine_verification_code'];
        } elseif ($class === 'hcaptcha') {
            $list = ['hcaptcha_secret', 'hcaptcha_site_key'];
        }

        foreach ($list as $item) {
            $setting = Config::where('item', $item)->find();
            $setting->value = input($item);
            $setting->save();
        }

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function emailIndex()
    {
        View::assign('smtp', Config::group('smtp'));
        return View::fetch('../app/view/admin/setting/email.html');
    }

    public function emailSave()
    {
        $list = ['smtp_host', 'smtp_username', 'smtp_password', 'smtp_port', 'smtp_name', 'smtp_sender'];

        foreach ($list as $item) {
            if ((string) input($item) === '') {
                return json(Tools::msg('0', '保存失败', '请填写所有项目'));
            }

            $setting = Config::where('item', $item)->find();
            $setting->value = input($item);
            $setting->save();
        }

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function emailPushTest()
    {
        $recipient = input('recipient/s');

        if ($recipient === '') {
            return json(Tools::msg('0', '发送失败', '请填写收件人'));
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '发送失败', '邮箱格式不规范'));
        }

        try {
            Notify::email($recipient, '测试邮件', '这是一封测试邮件。如果你能收到，则可确认邮件推送功能工作正常');
        } catch (\Exception $e) {
            return json(Tools::msg('0', '发送失败', $e->getMessage()));
        }

        return json(Tools::msg('1', '发送结果', '发送成功'));
    }

    public function telegramPushTest()
    {
        $recipient = input('recipient/s');

        if ($recipient === '') {
            return json(Tools::msg('0', '发送失败', '请填写收信用户 uid'));
        }

        try {
            Notify::telegram($recipient, '这是一条测试消息。如果你能收到，则可确认 Telegram 推送功能工作正常');
        } catch (\Exception $e) {
            return json(Tools::msg('0', '发送失败', $e->getMessage()));
        }

        return json(Tools::msg('1', '发送结果', '发送成功'));
    }

    public function telegramIndex()
    {
        View::assign('telegram', Config::group('telegram'));
        return View::fetch('../app/view/admin/setting/telegram.html');
    }

    public function telegramSave()
    {
        $list = ['telegram_account', 'telegram_token'];

        foreach ($list as $item) {
            if ((string) input($item) === '') {
                return json(Tools::msg('0', '保存失败', '请填写所有项目'));
            }

            $setting = Config::where('item', $item)->find();
            $setting->value = input($item);
            $setting->save();
        }

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function customIndex()
    {
        View::assign('custom', Config::group('custom'));
        return View::fetch('../app/view/admin/setting/custom.html');
    }

    public function customSave()
    {
        $list = ['custom_text', 'custom_script'];

        foreach ($list as $item) {
            $setting = Config::where('item', $item)->find();
            $setting->value = input($item);
            $setting->save();
        }

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }

    public function resolvIndex()
    {
        View::assign('config', Config::group('resolv'));
        return View::fetch('../app/view/admin/setting/resolv.html');
    }

    public function resolvSave()
    {
        $list = ['ali_whitelist', 'resolv_sync', 'sync_immediately_after_creation', 'ali_domain', 'ali_ak', 'ali_sk', 'ali_ttl'];

        foreach ($list as $item) {
            $setting = Config::where('item', $item)->find();
            $setting->value = input($item);
            $setting->save();
        }

        return json(Tools::msg('1', '保存结果', '保存成功'));
    }
}
