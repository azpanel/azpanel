<?php

namespace app\controller;

class AwsList
{
    public static function instanceSizes()
    {
        return [
            'c5.large' => '2 vCPU, 4 GiB 内存',
            'c5.xlarge' => '4 vCPU, 8 GiB 内存',
            'c5.2xlarge' => '8 vCPU, 16 GiB 内存',
            'c5.4xlarge' => '16 vCPU, 32 GiB 内存',
            'c5a.large' => '2 vCPU, 4 GiB 内存',
            'c5a.xlarge' => '4 vCPU, 8 GiB 内存',
            'c5a.2xlarge' => '8 vCPU, 16 GiB 内存',
            'c5a.4xlarge' => '16 vCPU, 32 GiB 内存',
            'c5a.8xlarge' => '32 vCPU, 64 GiB 内存',
            'c5n.large' => '2 vCPU, 5.25 GiB 内存',
            'c5n.xlarge' => '4 vCPU, 10.5 GiB 内存',
            'c5n.2xlarge' => '8 vCPU, 21 GiB 内存',
            'c5n.4xlarge' => '16 vCPU, 42 GiB 内存',
            't2.nano' => '1 vCPU, 0.5 GiB 内存',
            't2.micro' => '1 vCPU, 1 GiB 内存',
            't2.small' => '1 vCPU, 2 GiB 内存',
            't2.medium' => '2 vCPU, 4 GiB 内存',
            't2.large' => '2 vCPU, 8 GiB 内存',
            't2.xlarge' => '4 vCPU, 16 GiB 内存',
            't2.2xlarge' => '8 vCPU, 32 GiB 内存',
            't3.nano' => '2 vCPU, 0.5 GiB 内存',
            't3.micro' => '2 vCPU, 1 GiB 内存',
            't3.small' => '2 vCPU, 2 GiB 内存',
            't3.medium' => '2 vCPU, 4 GiB 内存',
            't3.large' => '2 vCPU, 8 GiB 内存',
            't3.xlarge' => '4 vCPU, 16 GiB 内存',
            't3.2xlarge' => '8 vCPU, 32 GiB 内存',
            't3a.nano' => '2 vCPU, 0.5 GiB 内存',
            't3a.micro' => '2 vCPU, 1 GiB 内存',
            't3a.small' => '2 vCPU, 2 GiB 内存',
            't3a.medium' => '2 vCPU, 4 GiB 内存',
            't3a.large' => '2 vCPU, 8 GiB 内存',
            't3a.xlarge' => '4 vCPU, 16 GiB 内存',
            't3a.2xlarge' => '8 vCPU, 32 GiB 内存',
        ];
    }

    public static function instanceRegion()
    {
        return [
            "us-east-1" => "美国东部（弗吉尼亚北部）",
            "us-east-2" => "美国东部（俄亥俄州）",
            "us-west-1" => "美国西部（加利福尼亚北部）",
            "us-west-2" => "美国西部（俄勒冈州）",
            "af-south-1" => "非洲（开普敦）",
            "ap-east-1" => "亚太地区（香港）",
            "ap-south-2" => "亚太地区（印度海得拉巴）",
            "ap-southeast-3" => "亚太地区（雅加达）",
            "ap-south-1" => "亚太地区（孟买）",
            "ap-northeast-3" => "亚太地区（大阪）",
            "ap-northeast-2" => "亚太地区（首尔）",
            "ap-southeast-1" => "亚太地区（新加坡）",
            "ap-southeast-2" => "亚太地区（悉尼）",
            "ap-northeast-1" => "亚太地区（东京）",
            "ca-central-1" => "加拿大（中部）",
            "eu-central-1" => "欧洲（法兰克福）",
            "eu-west-1" => "欧洲（爱尔兰）",
            "eu-west-2" => "欧洲（伦敦）",
            "eu-south-1" => "欧洲（米兰）",
            "eu-west-3" => "欧洲（巴黎）",
            "eu-south-2" => "欧洲（西班牙）",
            "eu-north-1" => "欧洲（斯德哥尔摩）",
            "eu-central-2" => "欧洲（苏黎世）",
            "me-south-1" => "中东（巴林）",
            "me-central-1" => "中东（阿联酋）",
            "sa-east-1" => "南美洲（巴西圣保罗）",
        ];
    }

    public static function instanceImage()
    {
        return [
            'debian-10' => [
                'imageOwner' => '136693071363',
                'imageName' => 'debian-10-amd64-2024*',
            ],
            'debian-11' => [
                'imageOwner' => '136693071363',
                'imageName' => 'debian-11-amd64-2024*',
            ],
            'debian-12' => [
                'imageOwner' => '136693071363',
                'imageName' => 'debian-12-amd64-2024*',
            ],
            'ubuntu-20.04' => [
                'imageOwner' => '099720109477',
                'imageName' => 'ubuntu/images/hvm-ssd/ubuntu-focal-20.04-amd64-server-2024*',
            ],
            'ubuntu-22.04' => [
                'imageOwner' => '099720109477',
                'imageName' => 'ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-2025*',
            ],
            // TODO: 完成密钥登入
            // 'windows-server-2022-chinese' => [
            //     'imageOwner' => '801119661308',
            //     'imageName' => 'Windows_Server-2022-Chinese_Simplified-Full-Base-*',
            // ],
            // 'windows-server-2022-english' => [
            //     'imageOwner' => '801119661308',
            //     'imageName' => 'Windows_Server-2022-English-Full-Base-*',
            // ],
        ];
    }
}
