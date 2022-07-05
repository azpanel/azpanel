<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Config extends Model
{
    protected $pk = 'id';
    protected $table = 'config';

    public static function obtain($key)
    {
        $item = self::where('item', $key)->find();
        
        // 网站目录下执行 php think migrate:run 修复

        if ($item->type == 'bool') {
            return (bool) $item->value;
        } elseif ($item->type == 'int') {
            return (int) $item->value;
        } else {
            return (string) $item->value;
        }
    }

    public static function class($name)
    {
        // 导入 database/config.sql 修复

        $items = self::where('class', $name)->select();
        
        foreach ($items as $item)
        {
            $info[$item->item] = $item->value;
        }

        return $info;
    }
}
