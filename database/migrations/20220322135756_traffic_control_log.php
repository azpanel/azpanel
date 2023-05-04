<?php

use think\migration\db\Column;
use think\migration\Migrator;

class TrafficControlLog extends Migrator
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
        $table = $this->table('traffic_control_log');
        $table->addColumn('rule_name', 'text', ['comment' => '规则名称', 'after' => 'rule_id'])
            ->addColumn('vm_name', 'text', ['comment' => '虚拟机名称', 'after' => 'vm_id'])
            ->update();
    }
}
