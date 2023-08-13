<?php

namespace app\controller;

use app\controller\AwsApi;
use app\controller\AwsList;
use app\controller\UserTask;
use app\model\Aws;
use think\facade\View;

class UserAws extends UserBase
{
    public function index()
    {
        $accounts = Aws::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->select();
        View::assign([
            'total' => $accounts->count(),
            'accounts' => $accounts,
        ]);
        return View::fetch('../app/view/user/aws/index.html');
    }

    public function searchAccount()
    {
        $s_name = input('s_name/s');
        $s_mark = input('s_mark/s');
        $s_status = input('s_status/d');

        $condition = [];
        $condition[] = ['user_id', '=', session('user_id')];
        ($s_mark !== '') && $condition[] = ['mark', $s_mark];
        ($s_name !== '') && $condition[] = ['email', 'like', '%' . $s_name . '%'];
        ($s_status !== 'all') && $condition[] = ['disable', '=', $s_status];

        $data = Aws::where($condition)
            ->field('id')
            ->select();

        // $sql = Db::getLastSql();

        return json(['result' => $data]);
    }

    public function create()
    {
        $notes = Aws::where('user_id', session('user_id'))
            ->field('mark')
            ->select()
            ->toArray();
        //dump($notes);
        View::assign([
            'notes' => $notes,
            'regions' => AwsList::instanceRegion(),
        ]);
        return View::fetch('../app/view/user/aws/create.html');
    }

    public static function awsCertificateVerify(array $params): bool
    {
        if ($params[0] === '') {
            throw new \Exception("邮箱不能为空");
        }
        if (!Tools::emailCheck($params[0])) {
            throw new \Exception("不是有效的邮箱：{$params[0]}");
        }
        if (strlen($params[2]) !== 20) {
            throw new \Exception("Access Key 长度不符要求：{$params[2]}");
        }
        if (strlen($params[3]) !== 40) {
            throw new \Exception("Secret Key 长度不符要求：{$params[3]}");
        }
        return true;
    }

    public function save()
    {
        $add_mode = input('add_mode/s');
        $regions = input('regions/a');
        $email = input('email/s');
        $passwd = input('passwd/s');
        $aws_ak = input('aws_ak/s');
        $aws_sk = input('aws_sk/s');
        $user_mark = input('user_mark/s');
        $batch_addition = input('batch_addition/s');
        $remark_filling = input('remark_filling/s');

        try {
            if ($add_mode === 'single') {
                $batch_addition = $email . PHP_EOL . $passwd . PHP_EOL . $aws_ak . PHP_EOL . $aws_sk;
            }
            $accounts = explode(PHP_EOL, $batch_addition);
            if (count($accounts) % 4 !== 0) {
                throw new \Exception("内容与数量不匹配");
            }
            $array = [];
            $pointer = 0;
            while ($pointer < count($accounts) - 1) {
                $email = $accounts[$pointer++];
                $passwd = $accounts[$pointer++];
                $aws_ak = $accounts[$pointer++];
                $aws_sk = $accounts[$pointer++];
                // check format
                self::awsCertificateVerify([
                    $email,
                    $passwd,
                    $aws_ak,
                    $aws_sk,
                ]);
                // query quota
                $quota = [];
                foreach ($regions as $region) {
                    $quota[$region] = AwsApi::getQuota($region, $aws_ak, $aws_sk);
                }
                // save data
                $array[] = [
                    'email' => $email,
                    'passwd' => $passwd,
                    'ak' => $aws_ak,
                    'sk' => $aws_sk,
                    'mark' => $remark_filling === 'input' ? $user_mark : $remark_filling,
                    'quota' => $quota,
                    'disable' => $quota['ap-northeast-1'] === 'null' ? 1 : 0,
                    'user_id' => session('user_id'),
                    'created_at' => time(),
                ];
            }
            Aws::insertAll($array);
            return json(Tools::msg('1', '保存结果', '保存成功'));
        } catch (\Exception $e) {
            return json(Tools::msg('0', '保存失败', $e->getMessage()));
        }
    }

    public function read($id)
    {
        $account = Aws::where('user_id', session('user_id'))->find($id);
        if ($account === null) {
            return View::fetch('../app/view/user/reject.html');
        }
        if (input('action/s') === 'queryQuota') {
            return json(AwsApi::getQuota(input('region/s'), $account->ak, $account->sk));
        }

        View::assign([
            'count' => 0,
            'account' => $account,
            'locations' => AwsList::instanceRegion(),
        ]);
        return View::fetch('../app/view/user/aws/read.html');
    }

    public function edit($id)
    {
        $account = Aws::where('user_id', session('user_id'))->find($id);
        if ($account === null) {
            return View::fetch('../app/view/user/reject.html');
        }

        View::assign('account', $account);
        return View::fetch('../app/view/user/aws/edit.html');
    }

    public function update($id)
    {
        try {
            $account = Aws::where('user_id', session('user_id'))->find($id);
            if (input('action/s') === 'refresh') {
                $quota = [];
                foreach ($account['quota'] as $key => $value) {
                    $quota[$key] = AwsApi::getQuota($key, $account->ak, $account->sk);
                }
                $account->quota = $quota;
                $account->disable = $quota['ap-northeast-1'] === 'null' ? 1 : 0;
                $account->save();
                return json(Tools::msg('1', '刷新结果', '刷新成功'));
            }
            if (input('action/s') === 'refreshAll') {
                $count = 0;
                $task_uuid = input('task_uuid/s');
                $accounts = Aws::where('user_id', session('user_id'))->select();
                $task_id = UserTask::create(session('user_id'), '刷新AWS账户订阅状态', [], $task_uuid);
                foreach ($accounts as $account) {
                    try {
                        $count++;
                        //sleep(2);
                        UserTask::update($task_id, $count / $accounts->count(), '正在刷新 ' . $account->email);
                        $quota = [];
                        foreach ($account['quota'] as $key => $value) {
                            $quota[$key] = AwsApi::getQuota($key, $account->ak, $account->sk);
                        }
                        $account->quota = $quota;
                        $account->disable = $quota['ap-northeast-1'] === 'null' ? 1 : 0;
                        $account->save();
                    } catch (\Exception $e) {
                        UserTask::end($task_id, true);
                    }
                }
                UserTask::end($task_id, false);
                return json(Tools::msg('1', '刷新结果', '刷新成功'));
            }
            $account->email = input('email/s');
            $account->passwd = input('passwd/s');
            $account->mark = input('mark/s');
            $account->ak = input('ak/s');
            $account->sk = input('sk/s');
            self::awsCertificateVerify([
                $account->email,
                $account->passwd,
                $account->ak,
                $account->sk,
            ]);
            $account->save();
            return json(Tools::msg('1', '修改结果', '修改成功'));
        } catch (\Exception $e) {
            return json(Tools::msg('0', '修改失败', $e->getMessage()));
        }
    }

    public function delete($id)
    {
        try {
            if ($id === '0') {
                Aws::where('user_id', session('user_id'))
                    ->where('disable', 1)
                    ->delete();
            } else {
                $account = Aws::where('user_id', session('user_id'))->find($id);
                $account->delete();
            }
            return json(Tools::msg('1', '删除结果', '删除成功'));
        } catch (\Exception $e) {
            return json(Tools::msg('0', '删除失败', $e->getMessage()));
        }
    }
}
