<?php

use think\migration\db\Column;
use think\migration\Migrator;

class AccountShareTable extends Migrator
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
        $table = $this->table('share');
        $table->addColumn('user_id', 'integer', ['comment' => '创建用户'])
            ->addColumn('token', 'text', ['comment' => '访问密钥'])
            ->addColumn('content', 'text', ['comment' => '分享内容'])
            ->addColumn('count', 'integer', ['comment' => '账户数量'])
            ->addColumn('is_use', 'integer', ['comment' => '使用标记', 'default' => 0])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->create();
    }
}
