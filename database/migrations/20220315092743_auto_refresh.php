<?php

use think\migration\db\Column;
use think\migration\Migrator;

class AutoRefresh extends Migrator
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
        $table = $this->table('auto_refresh');
        $table->addColumn('user_id', 'integer', ['comment' => '用户'])
            ->addColumn('rate', 'integer', ['comment' => '刷新频率'])
            ->addColumn('push_swicth', 'integer', ['comment' => '推送开关'])
            ->addColumn('function_swicth', 'integer', ['comment' => '刷新功能开关'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->addColumn('updated_at', 'integer', ['comment' => '更新时间'])
            ->create();
    }
}
