<?php

namespace app\controller;

use GuzzleHttp\Client;

class ProxyController extends UserBase
{
    public function test()
    {
        $addr = input('socks5_address/s');
        $port = input('socks5_port/d');
        $socks5_user = input('socks5_user/s');
        $socks5_passwd = input('socks5_passwd/s');

        try {
            // 检查参数
            if ($addr === '' || $port === '' || $port < 1 || $port > 65535) {
                throw new \Exception("代理服务器参数不正确");
            }
            // 配置参数
            $create_params = [];
            $create_params['timeout'] = 5;

            if ($socks5_user !== '' && $socks5_passwd !== '') {
                $create_params['proxy'] = "socks5://{$socks5_user}:{$socks5_passwd}@{$addr}:{$port}";
            } else {
                $create_params['proxy'] = "socks5://{$addr}:{$port}";
            }
            $client = new Client($create_params);
            // 发起测试请求
            $response = $client->request('GET', 'https://myip.ipip.net');
            $statusCode = (int) $response->getStatusCode();

            return json([
                'status' => $statusCode !== 204 ? true : false,
                'msg' => $response->getBody()->getContents(),
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => false,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
