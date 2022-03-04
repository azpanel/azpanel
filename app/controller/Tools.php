<?php
namespace app\controller;

use app\controller\Ip;

class Tools
{
    public static function encryption($text)
    {
        $hash = hash('sha512', $text, false);
        return $hash;
    }

    public static function isIpv4($target)
    {
        if (preg_match('#\d+\.\d+\.\d+\.\d+#', $target)) {
            return true;
        }

        return false;
    }

    public static function IpInfo($ip_addr)
    {
        if (!self::isIpv4($ip_addr)) {
            return 'null';
        }
        
        $ip = new Ip;
        $addr = $ip->ip2addr($ip_addr);
        $result = $addr['country'] . $addr['area'];
        return $result;
    }

    public static function Msg($code, $title, $content)
    {
        $body = [
            'status' => $code,
            'title' => $title,
            'content' => $content
        ];

        return $body;
    }

    public static function emailCheck($address)
    {
        return (!filter_var($address, FILTER_VALIDATE_EMAIL)) ? false : true;
    }
}
