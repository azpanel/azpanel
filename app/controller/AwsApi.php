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

    public static function createAWSClient(
        string $region,
        string $access_key,
        string $secret_key,
        bool $use_proxy = false,
        string $mode = 'quota'
    ) {
        $request_params = [
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $access_key,
                'secret' => $secret_key,
            ],
        ];
        if ($use_proxy) {
            // $request_params['http'] = [
            //     'proxy' => "socks5://{$proxy->addr}:{$proxy->port}",
            //     'connect_timeout' => 5,
            // ];
        }
        if ($mode === 'quota') {
            $client = new ServiceQuotasClient($request_params);
        } else {
            $client = new \Aws\Ec2\Ec2Client($request_params);
        }

        return $client;
    }

    public static function getRegionQuota(object $session, string $region)
    {
        $result = $session->getServiceQuota([
            'QuotaCode' => 'L-1216C47A',
            'ServiceCode' => 'ec2',
        ]);
        return intval($result['Quota']['Value']) ?? null;
    }

    public static function createVpc(object $session): string
    {
        $result = $session->createVpc([
            'CidrBlock' => '172.31.0.0/16',
            'AmazonProvidedIpv6CidrBlock' => true,
        ]);
        return $result['Vpc']['VpcId'];
    }

    public static function createSubnet(object $session, string $vpc_id): string
    {
        $subet_cidr = ['172.31.0.0/16', '172.31.0.0/20', '172.31.32.0/20'];
        $result = $session->createSubnet([
            'VpcId' => $vpc_id,
            'CidrBlock' => $subet_cidr[0],
        ]);
        return $result['Subnet']['SubnetId'];
    }

    public static function describeImages(object $session, string $imageName, string $imageOwner): string
    {
        $result = $session->describeImages([
            'Filters' => [
                [
                    'Name' => 'name',
                    'Values' => [
                        $imageName,
                    ],
                ],
            ],
            'Owners' => [
                $imageOwner,
            ],
        ]);
        return $result['Images'][0]['ImageId'];
    }

    public static function createKeyPair(object $session, string $name): void
    {
        $session->createKeyPair([
            'KeyName' => $name,
        ]);
    }

    public static function createSecurityGroup(object $session, string $name, ?string $vpc_id = null): string
    {
        $params = [
            'Description' => $name,
            'GroupName' => $name,
        ];
        if (isset($vpc_id)) {
            $params['VpcId'] = $vpc_id;
        }
        $result = $session->createSecurityGroup($params);
        return $result['GroupId'];
    }

    public static function authorizeSecurityGroupIngress(object $session, string $groupId, array $IpPermissions): void
    {
        $session->authorizeSecurityGroupIngress([
            'GroupId' => $groupId,
            'IpPermissions' => $IpPermissions,
        ]);
    }

    public static function runInstances(
        object $session,
        string $imageId,
        array $params,
        string $groupId,
        ?string $subnet_id = null
    ): string {
        $params = [
            'BlockDeviceMappings' => [
                [
                    'DeviceName' => '/dev/xvda',
                    'Ebs' => [
                        'VolumeSize' => $params['disk_size'],
                    ],
                ],
            ],
            'ImageId' => $imageId,
            'InstanceType' => $params['size'],
            'KeyName' => $params['name'],
            'MinCount' => 1,
            'MaxCount' => 1,
            'SecurityGroupIds' => [
                $groupId,
            ],
            'UserData' => base64_encode($params['userDataRaw']),
            'TagSpecifications' => [
                [
                    'ResourceType' => 'instance',
                    'Tags' => [
                        [
                            'Key' => 'Name',
                            'Value' => $params['name'],
                        ],
                    ],
                ],
            ],
        ];
        if (isset($subnet_id)) {
            $params['SubnetId'] = $subnet_id;
        }
        $result = $session->runInstances($params);
        return $result['Instances'][0]['InstanceId'];
    }

    public static function allocateAddress(object $session): array
    {
        $result = $session->allocateAddress([
            'Domain' => 'vpc',
        ]);
        return [
            $result['PublicIp'],
            $result['AllocationId'],
        ];
    }

    public static function waitForInstanceToRun(object $session, string $InstanceId): void
    {
        while (true) {
            $result = $session->describeInstances([
                'Filters' => [
                    [
                        'Name' => 'instance-id',
                        'Values' => [
                            $InstanceId,
                        ],
                    ],
                ],
            ]);
            if ($result['Reservations'][0]['Instances'][0]['State']['Name'] !== 'pending') {
                break;
            }
        }
    }

    public static function createInternetGateway(object $session): string
    {
        $result = $session->createInternetGateway();
        return $result['InternetGateway']['InternetGatewayId'];
    }

    public static function attachInternetGateway(object $session, string $internetGatewayId, string $vpc_id): void
    {
        $session->attachInternetGateway([
            'InternetGatewayId' => $internetGatewayId,
            'VpcId' => $vpc_id,
        ]);
    }

    public static function modifyVpcAttribute(object $session, string $vpc_id): void
    {
        $session->modifyVpcAttribute([
            'VpcId' => $vpc_id,
            'EnableDnsHostnames' => [
                'Value' => true,
            ],
        ]);
    }

    public static function associateAddress(object $session, string $AllocationId, string $subnet_id, string $InstanceId): void
    {
        $session->associateAddress([
            'AllocationId' => $AllocationId,
            'SubnetId' => $subnet_id,
            'InstanceId' => $InstanceId,
        ]);
    }

    public static function getNetworkInterfaceId(object $session, string $InstanceId): string
    {
        $result = $session->describeInstances([
            'Filters' => [
                [
                    'Name' => 'instance-id',
                    'Values' => [
                        $InstanceId,
                    ],
                ],
            ],
        ]);
        return $result['Reservations'][0]['Instances'][0]['NetworkInterfaces'][0]['NetworkInterfaceId'];
    }

    public static function getIpv6CidrBlock(object $session, string $vpc_id): string
    {
        $result = $session->describeVpcs([
            'VpcIds' => [
                $vpc_id,
            ],
        ]);
        return $result['Vpcs'][0]['Ipv6CidrBlockAssociationSet'][0]['Ipv6CidrBlock'];
    }

    public static function calculatingIpv6Subnets(string $ipv6_cidr): string
    {
        $subnets_64 = [];
        $networks = \IPTools\Network::parse($ipv6_cidr)->moveTo('64');
        foreach ($networks as $network) {
            $subnets_64[] = (string) $network;
        }
        return $subnets_64[array_rand($subnets_64)];
    }

    public static function associateSubnetCidrBlock(object $session, string $use_ipv6_subnet, string $subnet_id): void
    {
        $session->associateSubnetCidrBlock([
            'Ipv6CidrBlock' => $use_ipv6_subnet,
            'SubnetId' => $subnet_id,
        ]);
    }

    public static function assignIpv6Addresses(object $session, string $NetworkInterfaceId): string
    {
        $result = $session->assignIpv6Addresses([
            'NetworkInterfaceId' => $NetworkInterfaceId,
            'Ipv6AddressCount' => 1,
        ]);
        return $result['AssignedIpv6Addresses'][0];
    }

    public static function describeRouteTables(object $session, string $vpc_id): string
    {
        $result = $session->describeRouteTables([
            'Filters' => [
                [
                    'Name' => 'vpc-id',
                    'Values' => [
                        $vpc_id,
                    ],
                ],
            ],
        ]);
        return $result['RouteTables'][0]['Associations'][0]['RouteTableId'];
    }

    public static function describeInternetGateways(object $session, string $vpc_id): string
    {
        $result = $session->describeInternetGateways([
            'Filters' => [
                [
                    'Name' => 'attachment.vpc-id',
                    'Values' => [
                        $vpc_id,
                    ],
                ],
            ],
        ]);
        return $result['InternetGateways'][0]['InternetGatewayId'];
    }

    public static function createRoute(object $session, string $InternetGatewayId, string $RouteTableId): void
    {
        $session->createRoute([
            'DestinationIpv6CidrBlock' => '::/0',
            'GatewayId' => $InternetGatewayId,
            'RouteTableId' => $RouteTableId,
        ]);
        $session->createRoute([
            'DestinationCidrBlock' => '0.0.0.0/0',
            'GatewayId' => $InternetGatewayId,
            'RouteTableId' => $RouteTableId,
        ]);
    }

    public static function countRegionVpc(object $session, string $region): int
    {
        $result = $session->describeVpcs();
        return count($result['Vpcs']);
    }

    public static function getInstancePublicIpv4(object $session, string $instance_id): string
    {
        while (true) {
            $result = $session->describeInstances([
                'Filters' => [
                    [
                        'Name' => 'instance-id',
                        'Values' => [
                            $instance_id,
                        ],
                    ],
                ],
            ]);
            if (isset($result['Reservations'][0]['Instances'][0]['NetworkInterfaces'][0]['Association']['PublicIp'])) {
                break;
            }
            sleep(2);
        }
        return $result['Reservations'][0]['Instances'][0]['NetworkInterfaces'][0]['Association']['PublicIp'];
    }

    public static function createIpv6EC2(object $client, array $params): array
    {
        $name = $params['name'];
        $vpc_id = self::createVpc($client);
        $subnet_id = self::createSubnet($client, $vpc_id);
        $imageId = self::describeImages($client, $params['imageName'], $params['imageOwner']);
        self::createKeyPair($client, $name);
        $groupId = self::createSecurityGroup($client, $name, $vpc_id);
        self::authorizeSecurityGroupIngress($client, $groupId, $params['IpPermissions']);
        $instance_id = self::runInstances($client, $imageId, $params, $groupId, $subnet_id);
        $public_ip = self::allocateAddress($client); // array return
        self::waitForInstanceToRun($client, $instance_id);
        $internetGatewayId = self::createInternetGateway($client);
        self::attachInternetGateway($client, $internetGatewayId, $vpc_id);
        self::modifyVpcAttribute($client, $vpc_id);
        self::associateAddress($client, $public_ip[1], $subnet_id, $instance_id);
        $NetworkInterfaceId = self::getNetworkInterfaceId($client, $instance_id);
        $ipv6_cidr = self::getIpv6CidrBlock($client, $vpc_id);
        $use_ipv6_subnet = self::calculatingIpv6Subnets($ipv6_cidr);
        self::associateSubnetCidrBlock($client, $use_ipv6_subnet, $subnet_id);
        $ipv6_addr = self::assignIpv6Addresses($client, $NetworkInterfaceId); // return ipv6 address
        $RouteTableId = self::describeRouteTables($client, $vpc_id);
        $InternetGatewayId = self::describeInternetGateways($client, $vpc_id);
        self::createRoute($client, $InternetGatewayId, $RouteTableId);

        return [
            'vpc_id' => $vpc_id,
            'subnet_id' => $subnet_id,
            'instance_id' => $instance_id,
            'public_ip' => $public_ip[0],
            'ipv6_cidr' => $ipv6_cidr,
            'ipv6_addr' => $ipv6_addr,
        ];
    }

    public static function createOnlyIpv4EC2(object $client, array $params): array
    {
        $name = $params['name'];
        $imageId = self::describeImages($client, $params['imageName'], $params['imageOwner']);
        self::createKeyPair($client, $name);
        $groupId = self::createSecurityGroup($client, $name);
        self::authorizeSecurityGroupIngress($client, $groupId, $params['IpPermissions']);
        $instance_id = self::runInstances($client, $imageId, $params, $groupId);
        $public_ip = self::getInstancePublicIpv4($client, $instance_id);

        return [
            'instance_id' => $instance_id,
            'public_ip' => $public_ip,
        ];
    }
}
