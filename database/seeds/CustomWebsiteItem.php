<?php

use think\migration\Seeder;

class CustomWebsiteItem extends Seeder
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
                'item' => 'custom_text',
                'value' => '<a href="https://github.com/azpanel/azpanel">staff</a>',
                'class' => 'custom',
                'default_value' => '<a href="https://github.com/azpanel/azpanel">staff</a>',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'custom_script',
                'value' => '<script></script>',
                'class' => 'custom',
                'default_value' => '<script></script>',
                'type' => 'string',
            ]
        ];

        $conn = $this->table('config');
        $conn->insert($data)->saveData();
    }
}
