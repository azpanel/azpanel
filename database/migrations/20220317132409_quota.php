<?php

use think\migration\db\Column;
use think\migration\Migrator;

class Quota extends Migrator
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
    public function change()
    {
        $table = $this->table('azure');
        $table->addColumn('reg_capacity', 'integer', [
            'comment' => '是否注册Capacity提供商',
            'default' => '0',
            'after' => 'providers_register',
        ])->update();
    }
}
