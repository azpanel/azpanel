<?php

use think\migration\Seeder;

class VersionItem extends Seeder
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
        $this->table('config')->insert(
            [
                'id' => null,
                'item' => 'version',
                'value' => '1.0.0',
                'class' => 'system',
                'default_value' => '1.0.0',
                'type' => 'string',
            ]
        )->save();
    }
}
