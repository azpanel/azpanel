<?php

namespace app\controller;

use GuzzleHttp\Client;

class ProxyController extends UserBase
{
    public function test()
    {
        $addr = input('socks5_address/s');
        $port = input('socks5_port/d');

        try {
            if ($addr === '' || $port === '' || $port < 1 || $port > 65535) {
                throw new \Exception("代理服务器参数不正确");
            }
            $client = new Client([
                'proxy' => "socks5://{$addr}:{$port}",
                'timeout' => 5,
            ]);
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
