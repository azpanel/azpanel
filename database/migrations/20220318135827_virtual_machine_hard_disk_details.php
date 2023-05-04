<?php

use think\migration\db\Column;
use think\migration\Migrator;

class VirtualMachineHardDiskDetails extends Migrator
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
        $table = $this->table('azure_server');
        $table->addColumn('disk_details', 'text', [
            'comment' => '硬盘详情',
            'default' => null,
            'null' => true,
            'after' => 'vm_details',
        ])->update();
    }
}
