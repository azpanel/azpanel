<?php

use think\migration\db\Column;
use think\migration\Migrator;

class AddHcaptcha extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up()
    {
        $rows = [
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
            ],
        ];

        // this is a handy shortcut
        $this->insert('config', $rows);
    }

    public function down()
    {
        $this->execute('DELETE FROM config WHERE config.item = \'captcha_provider\'');
        $this->execute('DELETE FROM config WHERE config.item = \'hcaptcha_site_key\'');
        $this->execute('DELETE FROM config WHERE config.item = \'hcaptcha_secret\'');
    }
}
