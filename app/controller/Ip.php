<?php

namespace app\controller;

class Ip
{
    public $fh; //IP数据库文件句柄
    public $first; //第一条索引
    public $last; //最后一条索引
    public $total; //索引总数

    //构造函数
    public function __construct()
    {
        $this->fh = fopen('../storage/qqwry.dat', 'rb'); //qqwry.dat文件
        $this->first = $this->getLong4();
        $this->last = $this->getLong4();
        $this->total = ($this->last - $this->first) / 7; //每条索引7字节
    }

    //析构函数
    public function __destruct()
    {
        fclose($this->fh);
    }

    //检查IP合法性
    public function checkIp($ip)
    {
        $arr = explode('.', $ip);
        if (count($arr) !== 4) {
            return false;
        }
        for ($i = 0; $i < 4; $i++) {
            if ($arr[$i] < '0' || $arr[$i] > '255') {
                return false;
            }
        }
        return true;
    }

    public function getLong4()
    {
        //读取little-endian编码的4个字节转化为长整型数
        $result = unpack('Vlong', fread($this->fh, 4));
        return $result['long'];
    }

    public function getLong3()
    {
        //读取little-endian编码的3个字节转化为长整型数
        $result = unpack('Vlong', fread($this->fh, 3) . chr(0));
        return $result['long'];
    }

    //查询信息
    public function getInfo($data = '')
    {
        $char = fread($this->fh, 1);
        while (ord($char) !== 0) { //国家地区信息以0结束
            $data .= $char;
            $char = fread($this->fh, 1);
        }
        return $data;
    }

    //查询地区信息
    public function getArea()
    {
        $byte = fread($this->fh, 1); //标志字节
        switch (ord($byte)) {
            case 0:
                $area = '';
                break; //没有地区信息

            case 1: //地区被重定向
                fseek($this->fh, $this->getLong3());
                $area = $this->getInfo();
                break;
            case 2: //地区被重定向
                fseek($this->fh, $this->getLong3());
                $area = $this->getInfo();
                break;
            default:
                $area = $this->getInfo($byte);
                break; //地区没有被重定向
        }
        return $area;
    }

    public function ip2addr($ip)
    {
        if (!$this->checkIp($ip)) {
            return false;
        }
        $ip = pack('N', intval(ip2long($ip)));

        //二分查找
        $l = 0;
        $r = $this->total;
        while ($l <= $r) {
            $m = floor(($l + $r) / 2); //计算中间索引
            fseek($this->fh, $this->first + $m * 7);
            $beginip = strrev(fread($this->fh, 4)); //中间索引的开始IP地址
            fseek($this->fh, $this->getLong3());
            $endip = strrev(fread($this->fh, 4)); //中间索引的结束IP地址
            if ($ip < $beginip) { //用户的IP小于中间索引的开始IP地址时
                $r = $m - 1;
            } else {
                if ($ip > $endip) { //用户的IP大于中间索引的结束IP地址时
                    $l = $m + 1;
                } else { //用户IP在中间索引的IP范围内时
                    $findip = $this->first + $m * 7;
                    break;
                }
            }
        }

        //查询国家地区信息
        fseek($this->fh, $findip);
        $location = [];
        $location['beginip'] = long2ip($this->getLong4()); //用户IP所在范围的开始地址
        $offset = $this->getlong3();
        fseek($this->fh, $offset);
        $location['endip'] = long2ip($this->getLong4()); //用户IP所在范围的结束地址
        $byte = fread($this->fh, 1); //标志字节

        switch (ord($byte)) {
            case 1: //国家和区域信息都被重定向
                $countryOffset = $this->getLong3(); //重定向地址
                fseek($this->fh, $countryOffset);
                $byte = fread($this->fh, 1); //标志字节
                switch (ord($byte)) {
                    case 2: //国家信息被二次重定向
                        fseek($this->fh, $this->getLong3());
                        $location['country'] = $this->getInfo();
                        fseek($this->fh, $countryOffset + 4);
                        $location['area'] = $this->getArea();
                        break;
                    default: //国家信息没有被二次重定向
                        $location['country'] = $this->getInfo($byte);
                        $location['area'] = $this->getArea();
                        break;
                }
                break;
            case 2: //国家信息被重定向
                fseek($this->fh, $this->getLong3());
                $location['country'] = $this->getInfo();
                fseek($this->fh, $offset + 8);
                $location['area'] = $this->getArea();
                break;
            default: //国家信息没有被重定向
                $location['country'] = $this->getInfo($byte);
                $location['area'] = $this->getArea();
                break;
        }

        //gb2312 to utf-8（去除无信息时显示的CZ88.NET）
        foreach ($location as $k => $v) {
            $location[$k] = str_replace('CZ88.NET', '', iconv('gb2312', 'utf-8', $v));
        }

        return $location;
    }
}
