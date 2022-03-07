<?php
namespace app\controller;

use app\controller\Ip;
use think\facade\Request;

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

    public static function getClientIp()
    {
        $http_headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_ALI_CDN_REAL_IP',
            'True-Client-Ip',
            'X-Real-IP',
        ];

        foreach ($http_headers as $header) {
            if (isset($_SERVER[$header])) {
                $list = explode(',', $_SERVER[$header]);
                $client_ip = $list[0];
                break;
            }
        }

        if (!isset($client_ip)) {
            $client_ip = request()->ip();
        }

        return $client_ip;
    }
}
