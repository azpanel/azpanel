<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class LoginLog extends Model
{
    protected $pk = 'id';
    protected $table = 'login_log';

    protected function getStatusAttr($status)
    {
        switch ($status) {
            case 0:
                return '失败';
            case 1:
                return '成功';
        }
    }

    protected function getCreatedAtAttr($created_at)
    {
        return date('Y-m-d H:i:s', (int) $created_at);
    }
}
