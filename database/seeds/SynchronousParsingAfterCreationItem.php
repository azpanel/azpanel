<?php

use think\migration\Seeder;

class SynchronousParsingAfterCreationItem extends Seeder
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
                'item' => 'sync_immediately_after_creation',
                'value' => '0',
                'class' => 'resolv',
                'default_value' => '0',
                'type' => 'bool',
            ]
        ];

        $conn = $this->table('config');
        $conn->insert($data)->saveData();
    }
}
