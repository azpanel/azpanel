<?php

namespace app\controller;

use app\controller\Ip;
use app\model\Config;
use GuzzleHttp\Client;

class Tools
{
    public static function encryption($text)
    {
        return hash('sha512', $text, false);
    }

    public static function isIpv4($target)
    {
        if (preg_match('#\d+\.\d+\.\d+\.\d+#', $target)) {
            return true;
        }

        return false;
    }

    public static function ipInfo($ip_addr)
    {
        if (!self::isIpv4($ip_addr)) {
            return 'null';
        }

        $ip = new Ip();
        $addr = $ip->ip2addr($ip_addr);
        return $addr['country'] . $addr['area'];
    }

    public static function msg($code, $title, $content)
    {
        return [
            'status' => $code,
            'title' => $title,
            'content' => $content,
        ];
    }

    public static function emailCheck($address)
    {
        return !filter_var($address, FILTER_VALIDATE_EMAIL) ? false : true;
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

    public static function getUnixTimestamp()
    {
        // http://www.jsphp.net/php/show-12-640-1.html
        [$s1, $s2] = explode(' ', microtime());
        return (float) sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    public static function verifyHcaptcha($result): bool
    {
        // https://artisansweb.net/a-guide-on-hcaptcha-integration-with-php/

        $client = new Client([
            'base_uri' => 'https://hcaptcha.com',
        ]);

        $response = $client->request('POST', '/siteverify', [
            'form_params' => [
                'secret' => Config::obtain('hcaptcha_secret'),
                'response' => $result,
            ],
        ]);

        $body = $response->getBody();
        $object_body = json_decode($body);

        if ($object_body->success) {
            return true;
        }

        return false;
    }

    public static function getMailAddress($text)
    {
        // https://blog.csdn.net/weixin_39569112/article/details/115825074

        $pattern = "/([a-z0-9\-_\.]+@[a-z0-9]+\.[a-z0-9\-_\.]+)/";
        preg_match($pattern, $text, $matches);
        return $matches[0];
    }

    public static function getJsonContent($text)
    {
        // https://segmentfault.com/q/1010000002455112 @sogouo
        preg_match('/{(.*?)}/is', $text, $matches);
        return $matches[0];
    }
}
