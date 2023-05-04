<?php

namespace app\controller;

use AlibabaCloud\Client\AlibabaCloud;
use app\model\Config;

class Ali
{
    public static function count($rr): int
    {
        $configs = Config::group('resolv');
        AlibabaCloud::accessKeyClient($configs['ali_ak'], $configs['ali_sk'])
            ->regionId('cn-hongkong')
            ->asDefaultClient();

        $response = AlibabaCloud::rpc()
            ->product('Alidns')
            ->version('2015-01-09')
            ->action('DescribeDomainRecords')
            ->method('POST')
            ->options([
                'query' => [
                    'DomainName' => $configs['ali_domain'],
                    'RRKeyWord' => $rr,
                ],
            ])
            ->request()
            ->toArray();

        return $response['TotalCount'];
    }

    public static function createOrUpdate($rr, $ip)
    {
        if (self::count($rr) === 0) {
            return self::create($rr, $ip);
        }

        $configs = Config::group('resolv');
        AlibabaCloud::accessKeyClient($configs['ali_ak'], $configs['ali_sk'])
            ->regionId('cn-hongkong')
            ->asDefaultClient();

        AlibabaCloud::rpc()
            ->product('Alidns')
            ->version('2015-01-09')
            ->action('UpdateDomainRecord')
            ->method('POST')
            ->options([
                'query' => [
                    'Type' => 'A',
                    'RR' => $rr,
                    'Value' => $ip,
                    'TTL' => $configs['ali_ttl'],
                    'RecordId' => self::search($rr),
                ],
            ])
            ->request();
    }

    public static function search($rr)
    {
        $configs = Config::group('resolv');
        AlibabaCloud::accessKeyClient($configs['ali_ak'], $configs['ali_sk'])
            ->regionId('cn-hongkong')
            ->asDefaultClient();

        $response = AlibabaCloud::rpc()
            ->product('Alidns')
            ->version('2015-01-09')
            ->action('DescribeDomainRecords')
            ->method('POST')
            ->options([
                'query' => [
                    'DomainName' => $configs['ali_domain'],
                    'RRKeyWord' => $rr,
                ],
            ])
            ->request()
            ->toArray();

        if ($response['TotalCount'] > 1) {
            throw new \Exception('此记录有多个解析，请手动同步');
        }

        return $response['DomainRecords']['Record']['0']['RecordId'];
    }

    public static function create($rr, $ip)
    {
        $configs = Config::group('resolv');
        AlibabaCloud::accessKeyClient($configs['ali_ak'], $configs['ali_sk'])
            ->regionId('cn-hongkong')
            ->asDefaultClient();

        AlibabaCloud::rpc()
            ->product('Alidns')
            ->version('2015-01-09')
            ->action('AddDomainRecord')
            ->method('POST')
            ->options([
                'query' => [
                    'RR' => $rr,
                    'Type' => 'A',
                    'Value' => $ip,
                    'TTL' => $configs['ali_ttl'],
                    'DomainName' => $configs['ali_domain'],
                ],
            ])->request();
    }
}
