<?php

use think\migration\db\Column;
use think\migration\Migrator;

class VirtualMachineResize extends Migrator
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
        $table = $this->table('azure_server_resize');
        $table->addColumn('user_id', 'text', ['comment' => '提交用户'])
            ->addColumn('vm_id', 'text', ['comment' => '变更虚拟机'])
            ->addColumn('before_size', 'text', ['comment' => '变更前规格'])
            ->addColumn('after_size', 'text', ['comment' => '变更后规格'])
            ->addColumn('created_at', 'integer', ['limit' => 11, 'comment' => '变更时间'])
            ->create();
    }
}
