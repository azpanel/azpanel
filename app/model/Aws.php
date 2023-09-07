<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Aws extends Model
{
    protected $json = ['quota'];
    protected $jsonAssoc = true;

    public function judgmentState()
    {
        return $this->quota['ap-northeast-1'] === 'null' ? 'Disabled' : 'Enabled';
    }

    public function getQuotaText()
    {
        $regions = \app\controller\AwsList::instanceRegion();
        $text = '';
        foreach ($this->quota as $key => $value) {
            $text .= $regions[$key] . ($value !== 'null' ? ": {$value}V " : 'null ');
        }
        return $text;
    }
}
