<?php

use think\migration\Seeder;

class AddHcaptchaItem extends Seeder
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
                'item' => 'captcha_provider',
                'value' => 'think-captcha',
                'class' => 'verification_code',
                'default_value' => 'think-captcha',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'hcaptcha_site_key',
                'value' => '',
                'class' => 'verification_code',
                'default_value' => '',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'hcaptcha_secret',
                'value' => '',
                'class' => 'verification_code',
                'default_value' => '',
                'type' => 'string',
            ]
        ];

        $conn = $this->table('config');
        $conn->insert($data)->saveData();
    }
}
