<?php

use think\migration\db\Column;
use think\migration\Migrator;

class resolveConfig extends Migrator
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
            ],
        ];

        // this is a handy shortcut
        $this->insert('config', $rows);
    }

    public function down()
    {
        $this->execute('DELETE FROM config WHERE config.item = \'ali_whitelist\'');
        $this->execute('DELETE FROM config WHERE config.item = \'resolv_sync\'');
        $this->execute('DELETE FROM config WHERE config.item = \'ali_domain\'');
        $this->execute('DELETE FROM config WHERE config.item = \'ali_ak\'');
        $this->execute('DELETE FROM config WHERE config.item = \'ali_sk\'');
        $this->execute('DELETE FROM config WHERE config.item = \'ali_ttl\'');
    }
}
