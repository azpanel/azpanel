<?php

use think\migration\Seeder;

class DomainResolveConfigItem extends Seeder
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run(): void
    {
        $data = [
            [
                'id' => null,
                'item' => 'ali_whitelist',
                'value' => '1',
                'class' => 'resolv',
                'default_value' => '1',
                'type' => 'int',
            ],
            [
                'id' => null,
                'item' => 'resolv_sync',
                'value' => '0',
                'class' => 'resolv',
                'default_value' => '0',
                'type' => 'bool',
            ],
            [
                'id' => null,
                'item' => 'ali_domain',
                'value' => '',
                'class' => 'resolv',
                'default_value' => '',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'ali_ak',
                'value' => '',
                'class' => 'resolv',
                'default_value' => '',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'ali_sk',
                'value' => '',
                'class' => 'resolv',
                'default_value' => '',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'ali_ttl',
                'value' => '600',
                'class' => 'resolv',
                'default_value' => '600',
                'type' => 'string',
            ]
        ];

        $conn = $this->table('config');
        $conn->insert($data)->saveData();
    }
}
