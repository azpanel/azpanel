<?php
namespace app\controller;

use think\facade\Log;
use app\BaseController;
use app\model\Azure;
use app\model\AzureServer;
use app\controller\AzureList;
use GuzzleHttp\Client;

class AzureApi extends BaseController
{
    public static function getAzureAccessToken($account_id)
    {
        // https://docs.microsoft.com/zh-cn/azure/azure-netapp-files/azure-netapp-files-develop-with-rest-api

        $account = Azure::find($account_id);
        if (
            $account->az_token == null ||
            $account->az_token_updated_at == null ||
            (time() - $account->az_token_updated_at) > 3600
            ) {
            $account_configs = json_decode($account->az_api, true);
            $account_tenant_id = $account_configs['tenant'];
            $azure_token_url = 'https://login.microsoftonline.com/' . $account_tenant_id . '/oauth2/token';

            $client = new Client();
            $result = $client->post($azure_token_url, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $account_configs['appId'],
                    'client_secret' => $account_configs['password'],
                    'resource' => 'https://management.azure.com/',
                ]
            ]);

            $response = $result->getBody();
            $object = json_decode($response);

            $account->az_token = $object->access_token;
            $account->az_token_updated_at = time();
            $account->save();

            return $object->access_token;
        } else {
            return $account->az_token;
        }
    }

    public static function getAzureSubscription($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/subscriptions/list

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions?api-version=2020-01-01';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function registerMainAzureProviders($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/providers/register

        $azure_sub = Azure::find($account_id);

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/'. $azure_sub->az_sub_id . '/providers/Microsoft.Network/register?api-version=2021-04-01';
        $result = $client->post($url, [
            'headers' => $headers
        ]);

        sleep(1);

        $url = 'https://management.azure.com/subscriptions/'. $azure_sub->az_sub_id . '/providers/Microsoft.Compute/register?api-version=2021-04-01';
        $result = $client->post($url, [
            'headers' => $headers
        ]);

        sleep(1);

        $azure_sub->providers_register = 1;
        $azure_sub->save();
    }

    public static function getAzureResourceGroupsList($account_id, $az_sub_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resource-groups/list

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/'. $az_sub_id . '/resourcegroups?api-version=2021-04-01';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function getAzureResourceGroup($account_id, $group_name)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resources/list-by-resource-group

        $azure_sub = Azure::find($account_id);

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/'. $azure_sub->az_sub_id . '/resourcegroups/' . $group_name . '/resources?api-version=2021-04-01';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function getAzureValidResourceGroupsList($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resource-groups/list

        $resource_groups_list = self::getAzureResourceGroupsList($account_id);
        $azure_valid_resource_groups_list = [];

        foreach ($resource_groups_list['value'] as $resource_group)
        {
            if (!strstr($resource_group['name'], 'NetworkWatcher') &&
            !strstr($resource_group['name'], 'cloud-shell')) {
                array_push($azure_valid_resource_groups_list, $resource_group['name']);
            }
        }

        return $azure_valid_resource_groups_list;
    }

    public static function getAzureNetworkInterfacesDetails($account_id, $network_interface_name, $resource_group_name, $subscription_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/network-interfaces/get
        // https://www.jianshu.com/p/0cf79f4973f7

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $subscription_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/networkInterfaces/' . $network_interface_name . '?api-version=2021-02-01&%24expand=ipConfigurations%2FpublicIPAddress%2CnetworkSecurityGroup';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        return json_decode($result->getBody(), true); // array
    }

    public static function getAzureVirtualMachinePublicIpv4($server)
    {
        $network_details = self::getAzureNetworkInterfacesDetails($server->account_id, $server->network_interfaces, $server->resource_group, $server->at_subscription_id);
        $ip_address = $network_details['properties']['ipConfigurations']['0']['properties']['publicIPAddress']['properties']['ipAddress'] ?? 'null';

        return $ip_address;
    }

    public static function getAzureVirtualMachineStatus($account_id, $request_url)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/instance-view

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com' . $request_url . '/instanceView?api-version=2021-03-01';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        return json_decode($result->getBody(), true); // array
    }

    public static function getAzureVirtualMachines($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/list-all

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $count     = 0;
        $azure_sub = Azure::find($account_id);

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $azure_sub->az_sub_id . '/providers/Microsoft.Compute/virtualMachines?api-version=2021-07-01';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        $virtual_machines = json_decode($result->getBody(), true);

        // 移除已在 portal.azure.com 删除但仍存在与列表中的虚拟机
        $servers = AzureServer::where('account_id', $account_id)->select();
        $decode_data = json_encode($virtual_machines);
        foreach ($servers as $server)
        {
            if (!strpos($decode_data, $server->vm_id)) {
                AzureServer::where('vm_id', $server->vm_id)->delete();
            }
        }

        foreach ($virtual_machines['value'] as $virtual_machine)
        {
            $vm_id = $virtual_machine['properties']['vmId'];
            $exist = AzureServer::where('vm_id', $vm_id)->find();

            // 只添加没添加的
            if ($exist == null) {
                // 数据处理
                $count += 1;
                $resource_group     = explode('/', $virtual_machine['id']);
                $at_subscription_id = explode('/', $virtual_machine['id']);
                $network_interfaces = explode('/', $virtual_machine['properties']['networkProfile']['networkInterfaces']['0']['id']);
                $network_interfaces = end($network_interfaces);
                $instance_details   = self::getAzureVirtualMachineStatus($account_id, $virtual_machine['id']);
                
                // Log::write($instance_details, 'notice');

                // 新建
                $server = new AzureServer;
                $server->account_id         = $account_id;
                $server->user_id            = $azure_sub->user_id;
                $server->account_email      = $azure_sub->az_email;
                $server->name               = $virtual_machine['name'];
                $server->resource_group     = $resource_group['4'];
                $server->status             = $instance_details['statuses']['1']['code'];
                $server->location           = $virtual_machine['location'];
                $server->vm_size            = $virtual_machine['properties']['hardwareProfile']['vmSize'];
                $server->os_offer           = $virtual_machine['properties']['storageProfile']['imageReference']['offer'];
                $server->os_sku             = $virtual_machine['properties']['storageProfile']['imageReference']['sku'];$server->disk_size          = $virtual_machine['properties']['storageProfile']['osDisk']['diskSizeGB'] ?? 'null';
                $server->at_subscription_id = $at_subscription_id['2'];
                $server->vm_id              = $virtual_machine['properties']['vmId'];
                $server->network_interfaces = $network_interfaces;

                $network_details = self::getAzureNetworkInterfacesDetails($account_id, $network_interfaces, $resource_group['4'], $at_subscription_id['2']);
                $server->network_details    = json_encode($network_details);
                $server->ip_address         = $network_details['properties']['ipConfigurations']['0']['properties']['publicIPAddress']['properties']['ipAddress'] ?? 'null';

                $server->vm_details         = json_encode($virtual_machine);
                $server->instance_details   = json_encode($instance_details);
                $server->request_url        = $virtual_machine['id'];
                $server->created_at         = time();
                $server->updated_at         = time();
                $server->save();
            }
        }

        return $count;
    }

    public static function getAzureVirtualMachinesList($account_id, $az_sub_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/list-all

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $az_sub_id . '/providers/Microsoft.Compute/virtualMachines?api-version=2021-07-01';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        $virtual_machines = json_decode($result->getBody(), true);
        return $virtual_machines['value'];
    }

    public static function manageVirtualMachine($action, $account_id, $request_url)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/start
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/power-off
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/restart

        if ($action == 'stop') {
            $action = 'powerOff';
        }

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com' . $request_url . '/' . $action . '?api-version=2021-03-01';
        $result = $client->post($url, [
            'headers' => $headers
        ]);
    }

    public static function virtualMachinesDeallocate($account_id, $request_url)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/deallocate

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com' . $request_url . '/deallocate?api-version=2021-03-01';
        $result = $client->post($url, [
            'headers' => $headers
        ]);
    }

    public static function deleteAzureResourcesGroup($account_id, $subscription_id, $resource_group_name)
    {
        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $subscription_id . '/resourcegroups/' . $resource_group_name .'?api-version=2021-04-01';
        $result = $client->delete($url, [
            'headers' => $headers
        ]);
    }

    public static function deleteAzureResourcesGroupByUrl($url)
    {
        $group_url = explode('/', $url);
        $account = Azure::where('az_sub_id', $group_url['2'])->find();

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id)
        ];

        $client = new Client();
        $url = 'https://management.azure.com' . $url . '?api-version=2021-04-01';
        $result = $client->delete($url, [
            'headers' => $headers
        ]);
    }

    public static function createAzureResourceGroup($account, $resource_group_name, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resource-groups/create-or-update

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $body = [
            'location' => $location
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourcegroups/' . $resource_group_name . '?api-version=2021-04-01';
        $result = $client->put($url, [
            'headers' => $headers,
            'json' => $body
        ]);
    }

    public static function createAzurePublicNetworkIpv4($account, $ip_name, $resource_group_name, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/public-ip-addresses

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $body = [
            'location' => $location,
            'properties' => [
                'publicIPAllocationMethod' => 'Dynamic',
                'publicIPAddressVersion' => 'IPV4',
                'idleTimeoutInMinutes' => 10,
            ]
        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/publicIPAddresses/' . $ip_name . '?api-version=2021-03-01';

        $promise = $client->requestAsync('PUT', $url, [
            'headers' => $headers,
            'json' => $body
        ]);

        $promise->then(function (ResponseInterface $response) {
            $object = json_decode($response->getBody());
            return $object->id;
        });

        $result   = $promise->wait();
        $resource_url = json_decode($result->getBody());
        return $resource_url->id;
    }

    public static function createAzureVirtualNetwork($account, $virtual_network_name, $resource_group_name, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/virtual-networks/create-or-update

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $body = [
            'location' => $location,
            'properties' => [
                'addressSpace' => [
                    'addressPrefixes' => [
                        '10.0.0.0/16'
                    ]
                ]
            ]
        ];

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/virtualNetworks/' . $virtual_network_name . '?api-version=2021-03-01';

        $client = new Client();
        $result = $client->put($url, [
            'headers' => $headers,
            'json' => $body
        ]);
    }

    public static function createAzureVirtualNetworkSubnets($account, $virtual_network_name, $resource_group_name, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/subnets/create-or-update

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $body = [
            'location' => $location,
            'properties' => [
                'addressPrefix' => '10.0.0.0/16'
            ]
        ];

        $subnet_url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/virtualNetworks/' . $virtual_network_name . '/subnets/default?api-version=2021-03-01';

        $client = new Client();
        $result = $client->put($subnet_url, [
            'headers' => $headers,
            'json' => $body
        ]);

        $subnet_object = json_decode($result->getBody());

        return $subnet_object->id;
    }

    public static function createAzureVirtualNetworkInterfaces($account, $vm_name, $ip_url, $subnets_url, $location) {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/network-interfaces/create-or-update

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $body = [
            'location' => $location,
            'properties' => [
                'enableAcceleratedNetworking' => false,
                'ipConfigurations' => [
                    [
                        'name' => 'ipconfiguraion',
                        'properties' => [
                            'publicIPAddress' => [
                                'id' => $ip_url
                            ],
                            'subnet' => [
                                'id' => $subnets_url
                            ]
                        ]
                    ]
                ]
            ]

        ];

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $vm_name . '_group/providers/Microsoft.Network/networkInterfaces/' . $vm_name . '_vif?api-version=2021-03-01';

        $promise = $client->requestAsync('PUT', $url, [
            'headers' => $headers,
            'json' => $body
        ]);

        $promise->then(function (ResponseInterface $response) {
            $object = json_decode($response->getBody());
            return $object->id;
        });

        $object = $promise->wait();
        $result = json_decode($object->getBody());

        return $result->id;
    }

    public static function createAzureVm($account, $vm_name, $vm_config, $vm_image, $interfaces, $location) {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/create-or-update

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account->id),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $client = new Client();
        $images = AzureList::images();

        $body = [
            'id' => '/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $vm_name . '_group/providers/Microsoft.Compute/virtualMachines/' . $vm_name,
            'name' => $vm_name,
            'type' => 'Microsoft.Compute/virtualMachines',
            'location' => $location,
            'properties' => [
                'hardwareProfile' => [
                    'vmSize' => $vm_config['vm_size']
                ],
                'storageProfile' => [
                    'imageReference' => [
                        'sku' => $images[$vm_image]['sku'],
                        'offer' => $images[$vm_image]['offer'],
                        'version' => $images[$vm_image]['version'],
                        'publisher' => $images[$vm_image]['publisher'],
                    ],
                    'osDisk' => [
                        'diskSizeGB' => $vm_config['vm_disk_size'],
                        'createOption' => 'fromImage'
                    ],
                    'dataDisks' => []
                ],
                'osProfile' => [
                    'computerName'  => $vm_name,
                    'adminUsername' => $vm_config['vm_user'],
                    'adminPassword' => $vm_config['vm_passwd'],
                ],
                'networkProfile' => [
                    'networkInterfaces' => [
                        [
                            'id' => $interfaces
                        ]
                    ]
                ],
                'provisioningState' => 'succeeded'
            ]
        ];

        if ($vm_config['vm_script'] != 'null') {
            $body['properties']['osProfile']['customData'] = $vm_config['vm_script'];
        }

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $vm_name . '_group/providers/Microsoft.Compute/virtualMachines/' . $vm_name . '?api-version=2021-07-01';

        $object = $client->put($url,[
            'headers' => $headers,
            'json' => $body
        ]);

        $result = json_decode($object->getBody());

        return $result->id;
    }

    public static function getVirtualMachineStatistics($server, $start_time = null, $end_time = null)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/monitor/metric-definitions/list
        // https://docs.microsoft.com/zh-cn/rest/api/monitor/metrics/list

        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($server->account_id),
            'Content-Type' => 'application/json'
        ];

        if ($start_time == null || $end_time == null) {
            $start_time = date('Y-m-d\T H:00:00\Z', time() - 115200); // 24 + 8 h
            $end_time   = date('Y-m-d\T H:00:00\Z', time() - 25200);
        }
        
        $client = new Client();
        $url = 'https://management.azure.com' . $server->request_url . '/providers/Microsoft.Insights/metrics?api-version=2018-01-01&timespan=' . $start_time . '/' . $end_time . '&interval=PT1H&aggregation=total%2Caverage&metricnames=Percentage%20CPU%2CCPU%20Credits%20Remaining%2CAvailable%20Memory%20Bytes%2CNetwork%20In%20Total%2CNetwork%20Out%20Total';
        $result = $client->get($url, [
            'headers' => $headers
        ]);

        return json_decode($result->getBody(), true); // array
    }
}
