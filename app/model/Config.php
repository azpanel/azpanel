<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Config extends Model
{
    protected $pk = 'id';
    protected $table = 'config';

    public static function obtain($key)
    {
        $item = self::where('item', $key)->find()->toArray();

        // 网站目录下执行 php think migrate:run 修复

        switch ($item['type']) {
            case 'bool':
                return (bool) $item['value'];
            case 'int':
                return (int) $item['value'];
            default:
                return (string) $item['value'];
        }
    }

    public static function group($name)
    {
        // 导入 database/config.sql 修复

        $items = self::where('class', $name)->select();

        $info = [];
        foreach ($items as $item) {
            $info[$item->item] = $item->value;
        }

        return $info;
    }
}
