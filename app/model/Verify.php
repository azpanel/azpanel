<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Verify extends Model
{
    protected $pk = 'id';
    protected $table = 'verify';
}
