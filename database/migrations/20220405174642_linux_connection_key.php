<?php

use think\migration\Migrator;
use think\migration\db\Column;

class LinuxConnectionKey extends Migrator
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
        $table = $this->table('ssh_key');
        $table->addColumn('user_id', 'integer', array('comment' => '创建用户'))
            ->addColumn('name', 'text', array('comment' => '名称'))
            ->addColumn('public_key', 'text', array('comment' => '公钥'))
            ->addColumn('created_at', 'integer', array('comment' => '创建时间'))
            ->create();
    }
}
