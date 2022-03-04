<?php
namespace app\controller;

use app\controller\Ip;

class AzureList
{
    public static function images()
    {
        // 1. 在 https://portal.azure.com/#create/Microsoft.VirtualMachine 找发行者
        // 2. 在 https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machine-images/list-offers 找 offer
        // 3. 在 https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machine-images/list-skus 找 sku

        $images_list = [
            // Publishers/credativ/ArtifactTypes/VMImage/Offers/Debian/Skus/9
            'Debian_9' => [
                'display' => 'Debian 9',
                'sku' => '9',
                'publisher' => 'credativ',
                'version' => 'latest',
                'offer' => 'Debian',
            ],
            // Publishers/Debian/ArtifactTypes/VMImage/Offers/debian-10/Skus/10-gen2
            'Debian_10' => [
                'display' => 'Debian 10',
                'sku' => '10-gen2',
                'publisher' => 'Debian',
                'version' => 'latest',
                'offer' => 'debian-10',
            ],
            // Publishers/Debian/ArtifactTypes/VMImage/Offers/debian-11/Skus/11-gen2
            'Debian_11' => [
                'display' => 'Debian 11',
                'sku' => '11-gen2',
                'publisher' => 'Debian',
                'version' => 'latest',
                'offer' => 'debian-11',
            ],
            // Publishers/Canonical/ArtifactTypes/VMImage/Offers/0001-com-ubuntu-pro-trusty/Skus/pro-14_04-lts
            'Ubuntu_14_04' => [
                'display' => 'Ubuntu 14.04',
                'sku' => 'pro-14_04-lts',
                'publisher' => 'Canonical',
                'version' => 'latest',
                'offer' => '0001-com-ubuntu-pro-trusty',
            ],
            // Publishers/Canonical/ArtifactTypes/VMImage/Offers/0001-com-ubuntu-pro-xenial/Skus/pro-16_04-lts-gen2
            'Ubuntu_16_04' => [
                'display' => 'Ubuntu 16.04',
                'sku' => 'pro-16_04-lts-gen2',
                'publisher' => 'Canonical',
                'version' => 'latest',
                'offer' => '0001-com-ubuntu-pro-xenial',
            ],
            // Publishers/Canonical/ArtifactTypes/VMImage/Offers/0001-com-ubuntu-pro-bionic/Skus/pro-18_04-lts-gen2
            'Ubuntu_18_04' => [
                'display' => 'Ubuntu 18.04',
                'sku' => 'pro-18_04-lts-gen2',
                'publisher' => 'Canonical',
                'version' => 'latest',
                'offer' => '0001-com-ubuntu-pro-bionic',
            ],
            // Publishers/Canonical/ArtifactTypes/VMImage/Offers/0001-com-ubuntu-server-focal/Skus/20_04-lts-gen2
            'Ubuntu_20_04' => [
                'display' => 'Ubuntu 20.04',
                'sku' => '20_04-lts-gen2',
                'publisher' => 'Canonical',
                'version' => 'latest',
                'offer' => '0001-com-ubuntu-server-focal',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/centos-7/Skus/centos-7
            'Centos_7' => [
                'display' => 'Centos 7',
                'sku' => 'centos-7',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'centos-7',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/centos-7-6/Skus/centos-7-6
            'Centos_7_6' => [
                'display' => 'Centos 7.6',
                'sku' => 'centos-7-6',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'centos-7-6',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/centos-7-9/Skus/centos-7-9
            'Centos_7_9' => [
                'display' => 'Centos 7.9',
                'sku' => 'centos-7-9',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'centos-7-9',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/centos-8/Skus/centos-8
            'Centos_8' => [
                'display' => 'Centos 8',
                'sku' => 'centos-8',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'centos-8',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/centos-8-3/Skus/centos-8-3
            'Centos_8_3' => [
                'display' => 'Centos 8.3',
                'sku' => 'centos-8-3',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'centos-8-3',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/centos-8-5/Skus/centos-8-5
            'Centos_8_5' => [
                'display' => 'Centos 8.5',
                'sku' => 'centos-8-5',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'centos-8-5',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/rhel-7-9/Skus/rhel-7-9
            'Rhel_7_9' => [
                'display' => 'Rhel 7.9',
                'sku' => 'rhel-7-9',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'rhel-7-9',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/rhel-8-5/Skus/rhel-8-5
            'Rhel_8_5' => [
                'display' => 'Rhel 8.5',
                'sku' => 'rhel-8-5',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'rhel-8-5',
            ],
            // Publishers/procomputers/ArtifactTypes/VMImage/Offers/almalinux-8-5/Skus/almalinux-8-5
            'Almalinux_8_5' => [
                'display' => 'Almalinux 8.5',
                'sku' => 'almalinux-8-5',
                'publisher' => 'procomputers',
                'version' => 'latest',
                'offer' => 'almalinux-8-5',
            ],
        ];

        return $images_list;
    }

    public static function sizes()
    {
        // https://azureprice.net

        $sizes_list = [
            'Standard_B1ls' => [
                'cpu' => '1',
                'memory' => '0.5',
                'cost' => 0.0052 * 720,
            ],
            'Standard_B1s' => [
                'cpu' => '1',
                'memory' => '1',
                'cost' => 0.0104 * 720,
            ],
            'Standard_B1ms' => [
                'cpu' => '1',
                'memory' => '2',
                'cost' => 0.0207 * 720,
            ],
            'Standard_B2s' => [
                'cpu' => '2',
                'memory' => '4',
                'cost' => 0.0416 * 720,
            ],
            'Standard_B2ms' => [
                'cpu' => '2',
                'memory' => '8',
                'cost' => 0.0832 * 720,
            ],
            'Standard_B4ms' => [
                'cpu' => '4',
                'memory' => '16',
                'cost' => 0.1660 * 720,
            ],
            'Standard_F1s' => [
                'cpu' => '1',
                'memory' => '2',
                'cost' => 0.0497 * 720,
            ],
            'Standard_F2s' => [
                'cpu' => '2',
                'memory' => '4',
                'cost' => 0.0990 * 720,
            ],
            'Standard_F2s_v2' => [
                'cpu' => '2',
                'memory' => '4',
                'cost' => 0.0846 * 720,
            ],
            'Standard_F4s' => [
                'cpu' => '4',
                'memory' => '8',
                'cost' => 0.1990 * 720,
            ],
            'Standard_F4s_v2' => [
                'cpu' => '4',
                'memory' => '8',
                'cost' => 0.1690 * 720,
            ],
        ];

        return $sizes_list;
    }

    public static function locations()
    {
        // https://docs.microsoft.com/en-us/rest/api/resources/subscriptions/list-locations

        $locations_list = [
            // 常用
            'eastasia'           => '东亚 中国香港',
            'southeastasia'      => '东南亚 新加坡',
            'japaneast'          => '日本东部 东京',
            'japanwest'          => '日本西部 大阪',
            'koreacentral'       => '韩国中部 首尔',
            // 英国
            'uksouth'            => '英国南部 伦敦',
            'ukwest'             => '英国西部 加的夫',
            // 美国
            'eastus'             => '美国东部 弗吉尼亚',
            'westus'             => '美国西部 加利福尼亚',
            'eastus2'            => '美国东部2 弗吉尼亚',
            'westus2'            => '美国西部2 华盛顿',
            'westus3'            => '美国西部3 凤凰城',
            'centralus'          => '美国中部 爱荷华州',
            'southcentralus'     => '美国中南部 德克萨斯州',
            'westcentralus'      => '美国中西部 怀俄明州',
            'northcentralus'     => '美国中北部 伊利诺伊州',
            // 澳大利亚
            'australiaeast'      => '澳大利亚东部 新南威尔士州',
            'australiasoutheast' => '澳大利亚东南部 维多利亚',
            'australiacentral'   => '澳大利亚中部 堪培拉',
            // 加拿大
            'canadaeast'         => '加拿大东部 魁北克',
            'canadacentral'      => '加拿大中部 多伦多',
            // 欧洲
            'westeurope'         => '西欧 荷兰',
            'northeurope'        => '北欧 爱尔兰',
            'norwayeast'         => '挪威东部 挪威',
            'switzerlandnorth'   => '瑞士北部 苏黎世',
            'francecentral'      => '法国中部 巴黎',
            'swedencentral'      => '瑞典中部 耶夫勒',
            'germanywestcentral' => '德国中西部 法兰克福',
            // 印度
            'southindia'         => '印度南部 钦奈',
            'jioindiawest'       => '印度西部 贾姆纳格尔',
            'centralindia'       => '印度中部 浦那',
            // 其他
            'brazilsouth'        => '巴西南部 圣保罗州',
            'southafricanorth'   => '南非北部 约翰内斯堡',
            'uaenorth'           => '阿联酋北部 迪拜',
        ];

        return $locations_list;
    }

    public static function defaultPersonalise()
    {
        $personalise = [
            'vm_size'                => 'Standard_B2s',
            'vm_image'               => 'Debian_11',
            'vm_location'            => 'eastasia',
            'vm_disk_size'           => '32',
            'vm_default_script'      => '',
            'vm_default_identity'    => 'azuser',
            'vm_default_credentials' => 'Azure123456789',
        ];

        return json_encode($personalise);
    }
}
