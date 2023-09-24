<?php

use think\migration\Seeder;

class VerificationCodeSwitchItem extends Seeder
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
                'item' => 'registration_verification_code',
                'value' => '0',
                'class' => 'verification_code',
                'default_value' => '0',
                'type' => 'bool',
            ],
            [
                'id' => null,
                'item' => 'login_verification_code',
                'value' => '0',
                'class' => 'verification_code',
                'default_value' => '0',
                'type' => 'bool',
            ],
            [
                'id' => null,
                'item' => 'reset_password_verification_code',
                'value' => '0',
                'class' => 'verification_code',
                'default_value' => '0',
                'type' => 'bool',
            ],
            [
                'id' => null,
                'item' => 'create_virtual_machine_verification_code',
                'value' => '0',
                'class' => 'verification_code',
                'default_value' => '0',
                'type' => 'bool',
            ]
        ];

        $conn = $this->table('config');
        $conn->insert($data)->saveData();
    }
}
