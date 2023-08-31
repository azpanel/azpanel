<?php

namespace app\controller;

use app\controller\AwsList;
use app\controller\Tools;
use app\controller\UserTask;
use app\model\Aws;
use app\model\User;
use Aws\Ec2\Ec2Client;
use think\facade\View;
use think\helper\Str;

class UserAwsServer extends UserBase
{
    public function index()
    {
        $accounts = Aws::where('user_id', session('user_id'))
            ->where('disable', 0) // 只要没有被禁用的
            ->order('id', 'desc')
            ->select();

        View::assign([
            'accounts' => $accounts,
            'locations' => AwsList::instanceRegion(),
        ]);
        return View::fetch('../app/view/user/aws/server/index.html');
    }

    public function create()
    {
        $accounts = Aws::where('user_id', session('user_id'))
            ->where('disable', 0) // 只要没有被禁用的
            ->order('id', 'desc')
            ->select();

        $designated_id = (int) input('id');
        if ($designated_id !== 0) {
            $designated_account = Aws::where('user_id', session('user_id'))->find($designated_id);
            if ($designated_account === null) {
                return View::fetch('../app/view/user/reject.html');
            }
            View::assign('designated_account', $designated_account);
        }

        $user = User::find(session('user_id'));
        $personalise = json_decode($user->personalise, true);

        View::assign([
            'accounts' => $accounts,
            'personalise' => $personalise,
            'disk_sizes' => [16, 32, 64, 128],
            'sizes' => AwsList::instanceSizes(),
            'images' => AwsList::instanceImage(),
            'locations' => AwsList::instanceRegion(),
        ]);
        return View::fetch('../app/view/user/aws/server/create.html');
    }

    private static function getIpPermissions(): array
    {
        return [
            [
                'FromPort' => 0,
                'IpProtocol' => 'tcp',
                'IpRanges' => [
                    [
                        'CidrIp' => '0.0.0.0/0',
                        'Description' => 'All TCP',
                    ],
                ],
                'ToPort' => 65535,
            ],
            [
                'FromPort' => 0,
                'IpProtocol' => 'udp',
                'IpRanges' => [
                    [
                        'CidrIp' => '0.0.0.0/0',
                        'Description' => 'All UDP',
                    ],
                ],
                'ToPort' => 65535,
            ],
            [
                'FromPort' => -1,
                'IpProtocol' => 'icmp',
                'IpRanges' => [
                    [
                        'CidrIp' => '0.0.0.0/0',
                        'Description' => 'All ICMP',
                    ],
                ],
                'ToPort' => -1,
            ],
            [
                'FromPort' => -1,
                'IpProtocol' => 'icmpv6',
                'IpRanges' => [
                    [
                        'CidrIp' => '0.0.0.0/0',
                        'Description' => 'All ICMPV6',
                    ],
                ],
                'ToPort' => -1,
            ],
        ];
    }

    private static function getAWSClient(string $region, string $access_key, string $secret_key): object
    {
        $client = new Ec2Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $access_key,
                'secret' => $secret_key,
            ],
        ]);
        return $client;
    }

    private static function generateScriptContent(string $name, string $passwd, string $custom_script): string
    {
        $text = "#!/bin/bash\necho root:" . $passwd . "|sudo chpasswd root\nsudo rm -rf /etc/ssh/sshd_config\nsudo tee /etc/ssh/sshd_config <<EOF\nClientAliveInterval 120\nSubsystem       sftp    /usr/lib/openssh/sftp-server\nX11Forwarding yes\nPrintMotd no\nChallengeResponseAuthentication no\nPasswordAuthentication yes\nPermitRootLogin yes\nUsePAM yes\nAcceptEnv LANG LC_*\nEOF\nsudo systemctl restart sshd\n";
        $text .= "sudo hostnamectl set-hostname " . $name . "\n";
        if ($custom_script !== '') {
            $text .= $custom_script;
        }
        return $text;
    }

    public function save()
    {
        $vm_name = input('vm_name/s');
        $vm_remark = input('vm_remark/s');
        $vm_passwd = input('vm_passwd/s');
        $specified_size = input('specified_size/s');
        $vm_script = input('vm_script/s');
        $vm_location = input('vm_location/s');
        $vm_size = input('vm_size/s');
        $vm_account = input('vm_account/d');
        $vm_disk_size = input('vm_disk_size/d');
        $vm_image = input('vm_image/s');
        $task_uuid = input('task_uuid/s');

        // 空账户检查
        if ($vm_account === '') {
            return json(Tools::msg('0', '创建失败', '你还没有添加账户'));
        }
        // 所有权检查
        $account = Aws::find($vm_account);
        if ($account->user_id !== (int) session('user_id')) {
            return json(Tools::msg('0', '创建失败', '你不是此账户的持有者'));
        }
        // 虚拟机名称分隔
        if ($vm_remark === '') {
            $vm_remark = $vm_name;
        }
        $names = explode(',', $vm_name);
        $remarks = explode(',', $vm_remark);
        $vm_number = count($names);
        if (count($names) !== $vm_number || count($remarks) !== $vm_number || count($names) !== count($remarks)) {
            return json(Tools::msg('0', '创建失败', '请检查创建数量、备注和虚拟机名称是否正确分隔'));
        }
        // 虚拟机名称检查
        foreach ($names as $name) {
            if ($name === '') {
                return json(Tools::msg('0', '创建失败', '虚拟机名称不能为空'));
            }
        }
        foreach ($remarks as $remark) {
            if ($remark === '') {
                return json(Tools::msg('0', '创建失败', '虚拟机备注不能为空'));
            }
        }
        // 检查自定义脚本
        if (Str::contains($vm_script, '#!/bin/bash') || Str::contains($vm_script, '#!/bin/sh')) {
            return json(Tools::msg('0', '创建失败', '自定义脚本不需要以 #!/bin/bash 或 #!/bin/sh 开头，因为已经包含。可直接输入需要执行的代码。注意：部分命令可能需要 sudo'));
        }
        // 检查区域权限
        $quota = AwsApi::getQuota($vm_location, $account->ak, $account->sk);
        if ($quota === 'null') {
            return json(Tools::msg('0', '创建失败', '此账户在此区域可能未开通'));
        }
        // 记录创建参数
        $params = [
            'account' => [
                'id' => $account->id,
                'email' => $account->email,
                'quota' => $quota,
            ],
            'server' => [
                'name' => $vm_name,
                'mark' => $vm_remark,
                'size' => $vm_size,
                'count' => $vm_number,
                'image' => $vm_image,
                'location' => $vm_location,
                'disk_size' => $vm_disk_size,
                'script' => base64_encode($vm_script),
            ],
        ];
        // 初始化创建任务
        $progress = 0;
        $steps = $vm_number * 13;
        $task_id = UserTask::create(session('user_id'), '创建AWS虚拟机', $params, $task_uuid);
        // 开始创建
        foreach ($names as $vm_name) {
            $name = $vm_name . date('YmdHis', time());
            try {
                // 创建会话
                UserTask::update($task_id, (++$progress / $steps), '正在创建会话');
                $client = $this->getAWSClient($vm_location, $account->ak, $account->sk);
                // 创建 VPC
                UserTask::update($task_id, (++$progress / $steps), '正在创建 VPC');
                $result = $client->createVpc([
                    'CidrBlock' => '172.31.0.0/16', // VPC的CIDR块
                    'AmazonProvidedIpv6CidrBlock' => true, // 是否请求Amazon提供IPv6 CIDR块
                ]);
                $vpc_id = $result['Vpc']['VpcId'];
                // 创建子网
                UserTask::update($task_id, (++$progress / $steps), '正在创建子网');
                $result = $client->createSubnet([
                    'VpcId' => $vpc_id,
                    'CidrBlock' => '172.31.0.0/16',
                ]);
                $subnet_id = $result['Subnet']['SubnetId'];
                // 获取镜像 ID
                UserTask::update($task_id, (++$progress / $steps), '正在获取镜像 ID');
                $result = $client->describeImages([
                    'Filters' => [
                        [
                            'Name' => 'name',
                            'Values' => [
                                AwsList::instanceImage()[$vm_image]['imageName'],
                            ],
                        ],
                    ],
                    'Owners' => [
                        AwsList::instanceImage()[$vm_image]['imageOwner'],
                    ],
                ]);
                $image_id = $result['Images'][0]['ImageId'];
                // 创建密钥对
                UserTask::update($task_id, (++$progress / $steps), '正在创建密钥对');
                $client->createKeyPair([
                    'KeyName' => $name,
                ]);
                // 创建安全组
                UserTask::update($task_id, (++$progress / $steps), '正在创建安全组');
                $result = $client->createSecurityGroup([
                    'Description' => $name,
                    'GroupName' => $name,
                    'VpcId' => $vpc_id,
                ]);
                $group_id = $result['GroupId'];
                // 向指定的安全组添加入站规则
                UserTask::update($task_id, (++$progress / $steps), '正在向指定的安全组添加入站规则');
                $client->authorizeSecurityGroupIngress([
                    'GroupId' => $group_id,
                    'IpPermissions' => $this->getIpPermissions(),
                ]);
                // 执行开机指令
                UserTask::update($task_id, (++$progress / $steps), '正在创建虚拟机');
                $result = $client->runInstances([
                    'BlockDeviceMappings' => [
                        [
                            'DeviceName' => '/dev/xvda',
                            'Ebs' => [
                                'VolumeSize' => $vm_disk_size,
                            ],
                        ],
                    ],
                    'ImageId' => $image_id,
                    'InstanceType' => $specified_size === '' ? $vm_size : $specified_size,
                    'KeyName' => $name,
                    'MinCount' => 1,
                    'MaxCount' => 1,
                    'SecurityGroupIds' => [
                        $group_id,
                    ],
                    'SubnetId' => $subnet_id,
                    'UserData' => base64_encode($this->generateScriptContent($vm_name, $vm_passwd, $vm_script)),
                    'TagSpecifications' => [
                        [
                            'ResourceType' => 'instance',
                            'Tags' => [
                                [
                                    'Key' => 'Name',
                                    'Value' => $vm_name,
                                ],
                            ],
                        ],
                    ],
                ]);
                $instance_id = $result['Instances'][0]['InstanceId'];
                // 为 VPC 申请 IP 地址
                $result = $client->allocateAddress([
                    'Domain' => 'vpc',
                ]);
                $public_ip = $result['PublicIp'];
                $allocation_id = $result['AllocationId'];
                // 等待虚拟机正常运行
                UserTask::update($task_id, (++$progress / $steps), '正在等待虚拟机运行状态');
                while (true) {
                    $result = $client->describeInstances([
                        'Filters' => [
                            [
                                'Name' => 'instance-id',
                                'Values' => [
                                    $instance_id,
                                ],
                            ],
                        ],
                    ]);
                    if ($result['Reservations'][0]['Instances'][0]['State']['Name'] !== 'pending') {
                        break;
                    }
                }
                // 创建 Internet Gateway
                UserTask::update($task_id, (++$progress / $steps), '正在处理 IPv4 网络');
                $result = $client->createInternetGateway();
                $internet_gateway_id = $result['InternetGateway']['InternetGatewayId'];
                // 关联 Internet Gateway 和 VPC
                $client->attachInternetGateway([
                    'InternetGatewayId' => $internet_gateway_id,
                    'VpcId' => $vpc_id,
                ]);
                // 启用 VPC 的 DNS 主机名
                $client->modifyVpcAttribute([
                    'VpcId' => $vpc_id,
                    'EnableDnsHostnames' => [
                        'Value' => true,
                    ],
                ]);
                // 将 EIP 关联到子网
                $client->associateAddress([
                    'AllocationId' => $allocation_id,
                    'SubnetId' => $subnet_id,
                    'InstanceId' => $instance_id,
                ]);
                // 获取网络接口 ID
                UserTask::update($task_id, (++$progress / $steps), '正在处理 IPv6 网络');
                $result = $client->describeInstances([
                    'Filters' => [
                        [
                            'Name' => 'instance-id',
                            'Values' => [
                                $instance_id,
                            ],
                        ],
                    ],
                ]);
                $network_interface_id = $result['Reservations'][0]['Instances'][0]['NetworkInterfaces'][0]['NetworkInterfaceId'];
                // 获取 IPv6 CIDR
                $result = $client->describeVpcs([
                    'VpcIds' => [$vpc_id],
                ]);
                $ipv6_cidr = $result['Vpcs'][0]['Ipv6CidrBlockAssociationSet'][0]['Ipv6CidrBlock'];
                // 计算子网
                $subnets_64 = [];
                $networks = \IPTools\Network::parse($ipv6_cidr)->moveTo('64');
                foreach ($networks as $network) {
                    $subnets_64[] = (string) $network;
                }
                $use_subnet = $subnets_64[array_rand($subnets_64)];
                // 将一个新的IPv6 CIDR地址块关联到你的现有子网
                $client->associateSubnetCidrBlock([
                    'Ipv6CidrBlock' => $use_subnet,
                    'SubnetId' => $subnet_id,
                ]);
                // 将一个或多个IPv6地址分配给在Amazon VPC中运行的网络接口或实例
                $result = $client->assignIpv6Addresses([
                    'NetworkInterfaceId' => $network_interface_id,
                    'Ipv6AddressCount' => 1,
                ]);
                $ipv6_addr = $result['AssignedIpv6Addresses'][0];
                // 获取Amazon VPC中的一个或多个路由表的详细信息
                UserTask::update($task_id, (++$progress / $steps), '正在处理路由表');
                $result = $client->describeRouteTables([
                    'Filters' => [
                        [
                            'Name' => 'vpc-id',
                            'Values' => [$vpc_id],
                        ],
                    ],
                ]);
                $route_table_id = $result['RouteTables'][0]['Associations'][0]['RouteTableId'];
                // 获取一个或多个互联网网关的详细信息
                $result = $client->describeInternetGateways([
                    'Filters' => [
                        [
                            'Name' => 'attachment.vpc-id',
                            'Values' => [$vpc_id],
                        ],
                    ],
                ]);
                $internet_gateway_id = $result['InternetGateways'][0]['InternetGatewayId'];
                // 在Amazon VPC的路由表中创建一条新的路由
                $client->createRoute([
                    'DestinationIpv6CidrBlock' => '::/0',
                    'GatewayId' => $internet_gateway_id,
                    'RouteTableId' => $route_table_id,
                ]);
                $client->createRoute([
                    'DestinationCidrBlock' => '0.0.0.0/0',
                    'GatewayId' => $internet_gateway_id,
                    'RouteTableId' => $route_table_id,
                ]);
            } catch (\Exception $e) {
                $error = $e->getLine() . ':' . $e->getMessage();
                UserTask::end($task_id, true, ['msg' => $error]);
                return json(Tools::msg('0', '创建失败', $error));
            }
        }

        UserTask::end($task_id, false);
        sleep(1);
        return json(Tools::msg('1', '创建结果', '创建成功'));
    }

    public function read($id)
    {
        $account = Aws::where('user_id', session('user_id'))->find($id);
        try {
            $client = $this->getAWSClient(input('location/s'), $account->ak, $account->sk);
            return json($client->describeInstances()->toArray());
        } catch (\Exception $e) {
            return json([
                'ret' => 0,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function edit()
    {

    }

    public function update($id)
    {
        $account = Aws::where('user_id', session('user_id'))->find($id);
        try {
            $action = input('action/s');
            $location = input('location/s');
            $instances = input('instances/a');

            $client = $this->getAWSClient(input('location/s'), $account->ak, $account->sk);
            return json($client->$action(['InstanceIds' => $instances,])->toArray());
        } catch (\Exception $e) {
            return json([
                'ret' => 0,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function delete()
    {

    }
}
