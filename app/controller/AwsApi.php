<?php

namespace app\controller;

use app\BaseController;
use Aws\ServiceQuotas\ServiceQuotasClient;

class AwsApi extends BaseController
{
    public static function getQuota(string $region, string $ak, string $sk)
    {
        try {
            $client = new ServiceQuotasClient([
                'region' => $region,
                'version' => 'latest',
                'credentials' => [
                    'key' => $ak,
                    'secret' => $sk,
                ],
            ]);

            $result = $client->getServiceQuota([
                'QuotaCode' => 'L-1216C47A',
                'ServiceCode' => 'ec2',
            ]);
        } catch (\Exception $e) {
            return 'null';
        }

        return (int) $result['Quota']['Value'];
    }
}
