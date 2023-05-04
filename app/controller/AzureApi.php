<?php

namespace app\controller;

use app\BaseController;
use app\controller\AzureList;
use app\model\Azure;
use app\model\AzureServer;
use app\model\SshKey;
use GuzzleHttp\Client;
use think\helper\Str;

class AzureApi extends BaseController
{
    public static function getAzureAccessToken($account_id)
    {
        // https://docs.microsoft.com/zh-cn/azure/azure-netapp-files/azure-netapp-files-develop-with-rest-api

        $account = Azure::find($account_id);
        if (
            $account->az_token === null ||
            $account->az_token_updated_at === null ||
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
                ],
            ]);

            $response = $result->getBody();
            $object = json_decode($response);

            $account->az_token = $object->access_token;
            $account->az_token_updated_at = time();
            $account->save();

            return $object->access_token;
        }
        return $account->az_token;
    }

    public static function getToken($account_id, $more = false)
    {
        $headers = [
            'Authorization' => 'Bearer ' . self::getAzureAccessToken($account_id),
        ];

        if ($more) {
            $headers['Content-Type'] = 'application/json';
            $headers['Accept'] = 'application/json';
        }

        return $headers;
    }

    public static function getAzureSubscription($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/subscriptions/list

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions?api-version=2020-01-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account_id),
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function registerMainAzureProviders($client, $account, $provider)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/providers/register

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/providers/' . $provider . '/register?api-version=2021-04-01';
        $client->post($url, [
            'headers' => self::getToken($account->id),
        ]);
    }

    public static function getAzureResourceGroupsList($account_id, $az_sub_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resource-groups/list

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $az_sub_id . '/resourcegroups?api-version=2021-04-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account_id),
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function getAzureResourceGroup($account, $group_name)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resources/list-by-resource-group

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourcegroups/' . $group_name . '/resources?api-version=2021-04-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account->id),
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function getAzureValidResourceGroupsList($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resource-groups/list

        $resource_groups_list = self::getAzureResourceGroupsList($account_id);
        $azure_valid_resource_groups_list = [];

        foreach ($resource_groups_list['value'] as $resource_group) {
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

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $subscription_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/networkInterfaces/' . $network_interface_name . '?api-version=2021-02-01&%24expand=ipConfigurations%2FpublicIPAddress%2CnetworkSecurityGroup';
        $result = $client->get($url, [
            'headers' => self::getToken($account_id),
        ]);

        return json_decode($result->getBody(), true); // array
    }

    public static function getAzureVirtualMachineStatus($account_id, $request_url)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/instance-view

        $client = new Client();
        $url = 'https://management.azure.com' . $request_url . '/instanceView?api-version=2021-03-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account_id),
        ]);

        return json_decode($result->getBody(), true); // array
    }

    public static function getAzureVirtualMachines($account_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/list-all

        $count = 0;
        $azure_sub = Azure::find($account_id);

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $azure_sub->az_sub_id . '/providers/Microsoft.Compute/virtualMachines?api-version=2021-07-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account_id),
        ]);

        $virtual_machines = json_decode($result->getBody(), true);

        // 移除已在 portal.azure.com 删除但仍存在与列表中的虚拟机
        $servers = AzureServer::where('account_id', $account_id)->select();
        $encode_data = json_encode($virtual_machines);
        foreach ($servers as $server) {
            if (!strpos($encode_data, $server->vm_id)) {
                AzureServer::where('vm_id', $server->vm_id)->delete();
            }
        }

        foreach ($virtual_machines['value'] as $virtual_machine) {
            $vm_id = $virtual_machine['properties']['vmId'];
            $exist = AzureServer::where('vm_id', $vm_id)->find();

            // 只添加没添加的
            if ($exist === null) {
                // 数据处理
                $count += 1;
                $params = explode('/', $virtual_machine['id']);
                $network_interfaces = explode('/', $virtual_machine['properties']['networkProfile']['networkInterfaces']['0']['id']);
                $network_interfaces = end($network_interfaces);
                $instance_details = self::getAzureVirtualMachineStatus($account_id, $virtual_machine['id']);

                // 添加到列表
                $server = new AzureServer();
                $server->account_id = $account_id;
                $server->user_id = $azure_sub->user_id;
                $server->account_email = $azure_sub->az_email;
                $server->name = $virtual_machine['name'];
                $server->resource_group = $params['4'];
                $server->status = $instance_details['statuses']['1']['code'] ?? 'PowerState/running';
                $server->location = $virtual_machine['location'];
                $server->vm_size = $virtual_machine['properties']['hardwareProfile']['vmSize'];
                $server->os_offer = $virtual_machine['properties']['storageProfile']['imageReference']['offer'];
                $server->os_sku = $virtual_machine['properties']['storageProfile']['imageReference']['sku'];
                $server->disk_size = $virtual_machine['properties']['storageProfile']['osDisk']['diskSizeGB'] ?? 'null';
                $server->at_subscription_id = $params['2'];
                $server->vm_id = $virtual_machine['properties']['vmId'];
                $server->network_interfaces = $network_interfaces;

                $network_details = self::getAzureNetworkInterfacesDetails($account_id, $network_interfaces, $params['4'], $params['2']);
                $server->network_details = json_encode($network_details);
                $server->ip_address = $network_details['properties']['ipConfigurations']['0']['properties']['publicIPAddress']['properties']['ipAddress'] ?? 'null';
                if (isset($network_details['properties']['ipConfigurations']['1']['name'])) {
                    $server->ipv6_address = $network_details['properties']['ipConfigurations']['1']['properties']['publicIPAddress']['properties']['ipAddress'];
                }

                $server->vm_details = json_encode($virtual_machine);
                $server->instance_details = json_encode($instance_details);
                $server->request_url = $virtual_machine['id'];
                $server->created_at = time();
                $server->updated_at = time();
                $server->save();
            }
        }

        return $count;
    }

    public static function readAzureVirtualMachinesList($account_id, $az_sub_id)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/list-all

        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $az_sub_id . '/providers/Microsoft.Compute/virtualMachines?api-version=2021-07-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account_id),
        ]);

        $virtual_machines = json_decode($result->getBody(), true);
        return $virtual_machines['value'];
    }

    public static function manageVirtualMachine($action, $account_id, $request_url)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/start
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/power-off
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/restart

        if ($action === 'stop') {
            $action = 'powerOff';
        }

        $client = new Client();
        $url = 'https://management.azure.com' . $request_url . '/' . $action . '?api-version=2021-03-01';
        $client->post($url, [
            'headers' => self::getToken($account_id),
        ]);
    }

    public static function virtualMachinesDeallocate($account_id, $request_url)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/deallocate

        $client = new Client();
        $url = 'https://management.azure.com' . $request_url . '/deallocate?api-version=2021-03-01';
        $client->post($url, [
            'headers' => self::getToken($account_id),
        ]);
    }

    public static function deleteAzureResourcesGroup($account_id, $subscription_id, $resource_group_name)
    {
        $client = new Client();
        $url = 'https://management.azure.com/subscriptions/' . $subscription_id . '/resourcegroups/' . $resource_group_name . '?api-version=2021-04-01';
        $client->delete($url, [
            'headers' => self::getToken($account_id),
        ]);
    }

    public static function deleteAzureResourcesGroupByUrl($url)
    {
        $group_url = explode('/', $url);
        $account = Azure::where('az_sub_id', $group_url['2'])->find();

        $client = new Client();
        $url = 'https://management.azure.com' . $url . '?api-version=2021-04-01';
        $client->delete($url, [
            'headers' => self::getToken($account->id),
        ]);
    }

    public static function createAzureResourceGroup($client, $account, $resource_group_name, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/resources/resource-groups/create-or-update

        $body = [
            'location' => $location,
        ];

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourcegroups/' . $resource_group_name . '?api-version=2021-04-01';
        $client->put($url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);
    }

    public static function createNetworkSecurityGroups(
        $client,
        $account,
        $resource_group_name,
        $location,
        $name
    ) {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/network-security-groups/create-or-update

        $body = [
            'location' => $location,
            'properties' => [
                'securityRules' => [
                    [
                        'name' => 'allow_any_in',
                        'properties' => [
                            'protocol' => '*',
                            'sourcePortRange' => '*',
                            'destinationPortRange' => '*',
                            'sourceAddressPrefix' => '*',
                            'destinationAddressPrefix' => '*',
                            'access' => 'Allow',
                            'priority' => 100,
                            'direction' => 'Inbound',
                            'sourcePortRanges' => [],
                            'destinationPortRanges' => [],
                            'sourceAddressPrefixes' => [],
                            'destinationAddressPrefixes' => [],
                        ],
                    ],
                    [
                        'name' => 'allow_any_out',
                        'properties' => [
                            'protocol' => '*',
                            'sourcePortRange' => '*',
                            'destinationPortRange' => '*',
                            'sourceAddressPrefix' => '*',
                            'destinationAddressPrefix' => '*',
                            'access' => 'Allow',
                            'priority' => 110,
                            'direction' => 'Outbound',
                            'sourcePortRanges' => [],
                            'destinationPortRanges' => [],
                            'sourceAddressPrefixes' => [],
                            'destinationAddressPrefixes' => [],
                        ],
                    ],
                ],
            ],
        ];

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourcegroups/' . $resource_group_name . '/providers/Microsoft.Network/networkSecurityGroups/' . $name . '?api-version=2022-01-01';
        $result = $client->put($url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);
        $resource_url = json_decode($result->getBody());
        return $resource_url->id;
    }

    public static function createAzurePublicNetworkIpv4(
        $client,
        $account,
        $ip_name,
        $resource_group_name,
        $location,
        $create_ipv6
    ) {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/public-ip-addresses

        $label = Str::random($length = 10);
        $label = Str::lower($label);

        $body = [
            'location' => $location,
            'properties' => [
                'dnsSettings' => [
                    'domainNameLabel' => $label,
                ],
            ],
        ];

        if ($create_ipv6) {
            $body['sku'] = [
                'name' => 'Standard',
                'tier' => 'Regional',
            ];
            $body['properties']['publicIPAddressVersion'] = 'IPv4';
            $body['properties']['publicIPAllocationMethod'] = 'Static';
        }

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/publicIPAddresses/' . $ip_name . '?api-version=2021-03-01';

        $promise = $client->requestAsync('PUT', $url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);

        $promise->then(static function (ResponseInterface $response) {
            $object = json_decode($response->getBody());
            return $object->id;
        });

        $result = $promise->wait();
        $resource_url = json_decode($result->getBody());
        return $resource_url->id;
    }

    public static function createAzurePublicNetworkIpv6($client, $account, $ip_name, $resource_group_name, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/public-ip-addresses

        $label = Str::random($length = 10);
        $label = Str::lower($label);

        $body = [
            'sku' => [
                'name' => 'Standard',
                'tier' => 'Regional',
            ],
            'location' => $location,
            'properties' => [
                'publicIPAddressVersion' => 'IPv6',
                'publicIPAllocationMethod' => 'Static',
                'dnsSettings' => [
                    'domainNameLabel' => $label,
                ],
            ],
        ];

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/publicIPAddresses/' . $ip_name . '?api-version=2021-03-01';

        $promise = $client->requestAsync('PUT', $url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);

        $promise->then(static function (ResponseInterface $response) {
            $object = json_decode($response->getBody());
            return $object->id;
        });

        $result = $promise->wait();
        $resource_url = json_decode($result->getBody());
        return $resource_url->id;
    }

    public static function countAzurePublicNetworkIpv4($client, $account, $location): int
    {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/public-ip-addresses/list-all

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/providers/Microsoft.Network/publicIPAddresses?api-version=2022-01-01';
        $result = $client->get($url, [
            'headers' => self::getToken($account->id, true),
        ]);

        $count = 0;
        $lists = json_decode($result->getBody(), true);

        foreach ($lists['value'] as $list) {
            if ($list['location'] === $location) {
                $count += 1;
            }
        }

        return $count;
    }

    public static function createAzureVirtualNetwork(
        $client,
        $account,
        $virtual_network_name,
        $resource_group_name,
        $location,
        $create_ipv6
    ) {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/virtual-networks/create-or-update

        $body = [
            'location' => $location,
            'properties' => [
                'addressSpace' => [
                    'addressPrefixes' => [
                        '10.0.0.0/16',
                    ],
                ],
            ],
        ];

        if ($create_ipv6) {
            $body['properties']['addressSpace']['addressPrefixes'] = [
                '10.0.0.0/16',
                'ace:ceb:deca::/48',
            ];
        }

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/virtualNetworks/' . $virtual_network_name . '?api-version=2021-03-01';

        $client->put($url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);
    }

    public static function createAzureVirtualNetworkSubnets(
        $client,
        $account,
        $virtual_network_name,
        $resource_group_name,
        $location,
        $create_ipv6
    ) {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/subnets/create-or-update
        // https://luotianyi.vc/5607.html

        $body = [
            'location' => $location,
            'properties' => [
                'addressPrefix' => '10.0.0.0/16',
            ],
        ];

        if ($create_ipv6) {
            unset($body['properties']['addressPrefix']);
            $body['properties']['addressPrefixes'] = [
                '10.0.0.0/16',
                'ace:ceb:deca:deed::/64',
            ];
        }

        $subnet_url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $resource_group_name . '/providers/Microsoft.Network/virtualNetworks/' . $virtual_network_name . '/subnets/default?api-version=2021-03-01';

        $result = $client->put($subnet_url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);

        $subnet_object = json_decode($result->getBody());

        return $subnet_object->id;
    }

    public static function createAzureVirtualNetworkInterfaces(
        $client,
        $account,
        $vm_name,
        $ipv4_url,
        $ipv6_url,
        $subnets_url,
        $location,
        $vm_size,
        $create_ipv6,
        $security_group_id
    ) {
        // https://docs.microsoft.com/zh-cn/rest/api/virtualnetwork/network-interfaces/create-or-update

        $body = [
            'location' => $location,
            'properties' => [
                'enableAcceleratedNetworking' => false,
                'ipConfigurations' => [
                    [
                        'name' => 'ipconfiguraion_v4',
                        'properties' => [
                            'publicIPAddress' => [
                                'id' => $ipv4_url,
                            ],
                            'subnet' => [
                                'id' => $subnets_url,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($create_ipv6) {
            $body['properties']['ipConfigurations']['0']['properties']['primary'] = true;
            $body['properties']['ipConfigurations'][] = [
                'name' => 'ipconfiguraion_v6',
                'properties' => [
                    'privateIPAddressVersion' => 'IPv6',
                    'publicIPAddress' => [
                        'id' => $ipv6_url,
                    ],
                    'subnet' => [
                        'id' => $subnets_url,
                    ],
                ],
            ];
            $body['properties']['networkSecurityGroup'] = [
                'id' => $security_group_id,
            ];
        }

        // With the GA of AN, region limitations have been removed, making the feature widely available around the world. Supported VM series include D/DSv2, D/DSv3, E/ESv3, F/FS, FSv2, and Ms/Mms.

        $sizes_list = AzureList::sizes();
        if (isset($sizes_list[$vm_size]['acc'])) {
            if ($sizes_list[$vm_size]['acc']) {
                $body['properties']['enableAcceleratedNetworking'] = true;
            }
        }

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $vm_name . '_group/providers/Microsoft.Network/networkInterfaces/' . $vm_name . '_vif?api-version=2021-03-01';

        $promise = $client->requestAsync('PUT', $url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);

        $promise->then(static function (ResponseInterface $response) {
            $object = json_decode($response->getBody());
            return $object->id;
        });

        $object = $promise->wait();
        $result = json_decode($object->getBody());

        return $result->id;
    }

    public static function createAzureVm($client, $account, $vm_name, $vm_config, $vm_image, $interfaces, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/virtual-machines/create-or-update

        $images = AzureList::images();

        $body = [
            'id' => '/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $vm_name . '_group/providers/Microsoft.Compute/virtualMachines/' . $vm_name,
            'name' => $vm_name,
            'type' => 'Microsoft.Compute/virtualMachines',
            'location' => $location,
            'properties' => [
                'hardwareProfile' => [
                    'vmSize' => $vm_config['vm_size'],
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
                        'createOption' => 'fromImage',
                    ],
                    'dataDisks' => [],
                ],
                'osProfile' => [
                    'computerName' => $vm_name,
                    'adminUsername' => $vm_config['vm_user'],
                ],
                'networkProfile' => [
                    'networkInterfaces' => [
                        [
                            'id' => $interfaces,
                        ],
                    ],
                ],
                'provisioningState' => 'succeeded',
            ],
        ];

        if ($vm_config['vm_script'] !== null) {
            $body['properties']['osProfile']['customData'] = $vm_config['vm_script'];
        }

        if ((int) $vm_config['vm_ssh_key'] === 0) {
            $body['properties']['osProfile']['adminPassword'] = $vm_config['vm_passwd'];
        } else {
            $ssh_key = SshKey::find($vm_config['vm_ssh_key']);
            $body['properties']['osProfile']['linuxConfiguration'] = [
                'disablePasswordAuthentication' => true,
                'ssh' => [
                    'publicKeys' => [
                        [
                            'path' => '/home/' . $vm_config['vm_user'] . '/.ssh/authorized_keys',
                            'keyData' => $ssh_key->public_key,
                        ],
                    ],
                ],
            ];
        }

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/resourceGroups/' . $vm_name . '_group/providers/Microsoft.Compute/virtualMachines/' . $vm_name . '?api-version=2021-07-01';

        $object = $client->put($url, [
            'headers' => self::getToken($account->id, true),
            'json' => $body,
        ]);

        $result = json_decode($object->getBody());

        return $result->id;
    }

    public static function getVirtualMachineStatistics($server, $start_time = null, $end_time = null)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/monitor/metric-definitions/list
        // https://docs.microsoft.com/zh-cn/rest/api/monitor/metrics/list

        if ($start_time === null || $end_time === null) {
            $start_time = date('Y-m-d\T H:00:00\Z', time() - 115200); // 24 + 8 h
            $end_time = date('Y-m-d\T H:00:00\Z', time() - 25200);
        }

        $client = new Client();
        $url = 'https://management.azure.com' . $server->request_url . '/providers/Microsoft.Insights/metrics?api-version=2018-01-01&timespan=' . $start_time . '/' . $end_time . '&interval=PT1H&aggregation=total%2Caverage&metricnames=Percentage%20CPU%2CCPU%20Credits%20Remaining%2CAvailable%20Memory%20Bytes%2CNetwork%20In%20Total%2CNetwork%20Out%20Total';
        $result = $client->get($url, [
            'headers' => self::getToken($server->account_id, true),
        ]);

        return json_decode($result->getBody(), true); // array
    }

    public static function virtualMachinesResize($new_size, $location, $account_id, $request_url)
    {
        // https://stackoverflow.com/questions/65444722/how-to-downgrade-and-upgrade-azure-virtual-machine-programmatically

        $body = [
            'location' => $location,
            'properties' => [
                'hardwareProfile' => [
                    'vmSize' => $new_size,
                ],
            ],
        ];

        $url = 'https://management.azure.com' . $request_url . '?api-version=2021-11-01';

        $client = new Client();
        $client->put($url, [
            'headers' => self::getToken($account_id, true),
            'json' => $body,
        ]);
    }

    public static function virtualMachinesRedisk($new_size, $server)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/disks/create-or-update

        //$disk_tiers = AzureList::diskTiers();
        $vm_details = json_decode($server->vm_details, true);
        $vm_disk_name = $vm_details['properties']['storageProfile']['osDisk']['name'];
        $vm_image_version = $vm_details['properties']['storageProfile']['imageReference']['exactVersion'];
        $vm_image_publishers = $vm_details['properties']['storageProfile']['imageReference']['publisher'];

        $body = [
            'location' => $server->location,
            'properties' => [
                'creationData' => [
                    'createOption' => 'FromImage',
                    'imageReference' => [
                        'id' => '/Subscriptions/' . $server->at_subscription_id . '/Providers/Microsoft.Compute/Locations/' . $server->location . '/Publishers/' . $vm_image_publishers . '/ArtifactTypes/VMImage/Offers/' . $server->os_offer . '/Skus/' . $server->os_sku . '/Versions/' . $vm_image_version,
                    ],
                ],
                'diskSizeGB' => $new_size,
                //'diskMBpsReadWrite' => $disk_tiers[$new_tier]['diskMBpsReadWrite'],
                //'diskIOPSReadWrite' => $disk_tiers[$new_tier]['diskIOPSReadWrite'],
                //'tier' => $new_tier
            ],
        ];

        $url = 'https://management.azure.com/subscriptions/' . $server->at_subscription_id . '/resourceGroups/' . $server->resource_group . '/providers/Microsoft.Compute/disks/' . $vm_disk_name . '?api-version=2021-04-01';

        $client = new Client();
        $client->put($url, [
            'headers' => self::getToken($server->account_id, true),
            'json' => $body,
        ]);
    }

    public static function getQuota($account, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/reserved-vm-instances/quota/list

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/providers/Microsoft.Capacity/resourceProviders/Microsoft.Compute/locations/' . $location . '/serviceLimits?api-version=2020-10-25';

        $client = new Client();
        $result = $client->get($url, [
            'headers' => self::getToken($account->id, true),
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function getDisks($server)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/disks/get

        $vm_details = json_decode($server->vm_details, true);
        $disk_name = $vm_details['properties']['storageProfile']['osDisk']['name'];

        $url = 'https://management.azure.com/subscriptions/' . $server->at_subscription_id . '/resourceGroups/' . $server->resource_group . '/Providers/Microsoft.Compute/disks/' . $disk_name . '?api-version=2020-12-01';

        $client = new Client();
        $result = $client->get($url, [
            'headers' => self::getToken($server->account_id, true),
        ]);

        return json_decode($result->getBody(), true);
    }

    public static function getResourceSkusList($client, $account, $location)
    {
        // https://docs.microsoft.com/zh-cn/rest/api/compute/resource-skus/list

        $url = 'https://management.azure.com/subscriptions/' . $account->az_sub_id . '/Providers/Microsoft.Compute/skus/?api-version=2019-04-01&$filter=location eq ' . "'" . $location . "'";

        $result = $client->get($url, [
            'headers' => self::getToken($account->id, true),
        ]);

        return json_decode($result->getBody(), true);
    }
}
