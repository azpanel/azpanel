<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AccountRecycleBin extends Migrator
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
        $table = $this->table('azure_recycle');
        $table->addColumn('user_id', 'integer', array('comment' => '归属用户'))
            ->addColumn('az_email', 'text', array('comment' => '登录账户'))
            ->addColumn('az_sub_type', 'text', array('comment' => '账户类型'))
            ->addColumn('user_mark', 'text', array('comment' => '用户备注'))
            ->addColumn('bill_charges', 'text', array('comment' => '预估费用', 'default' => null, 'null' => true))
            ->addColumn('life_cycle', 'text', array('comment' => '账户生命周期'))
            ->addColumn('created_at', 'integer', array('comment' => '创建时间'))
            ->create();
    }
}
