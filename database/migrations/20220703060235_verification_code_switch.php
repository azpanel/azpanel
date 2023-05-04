<?php

use think\migration\db\Column;
use think\migration\Migrator;

class VerificationCodeSwitch extends Migrator
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
            ],
        ];

        // this is a handy shortcut
        $this->insert('config', $rows);
    }

    public function down()
    {
        $this->execute('DELETE FROM config WHERE config.item = \'registration_verification_code\'');
        $this->execute('DELETE FROM config WHERE config.item = \'login_verification_code\'');
        $this->execute('DELETE FROM config WHERE config.item = \'reset_password_verification_code\'');
        $this->execute('DELETE FROM config WHERE config.item = \'create_virtual_machine_verification_code\'');
    }
}
