<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class AutoRefresh extends Model
{
    protected $pk = 'id';
    protected $table = 'auto_refresh';
}
