<?php

use think\migration\Migrator;
use think\migration\db\Column;

class ProxySet extends Migrator
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
        $table = $this->table('proxy');
        $table->addColumn('user_id', 'integer', ['comment' => '创建用户'])
            ->addColumn('name', 'text', ['comment' => '代理名称'])
            ->addColumn('proxy', 'text', ['comment' => '代理'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->addColumn('updated_at', 'integer', ['comment' => '修改时间'])
            ->create();

        $table = $this->table('azure');
        $table->addColumn('proxy', 'integer', array(
            'comment' => '使用代理',
            'default' => '0',
            'after' => 'providers_register'
        ))->update();
    }
}
