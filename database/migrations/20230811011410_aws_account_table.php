<?php

use think\migration\db\Column;
use think\migration\Migrator;

class AwsAccountTable extends Migrator
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
        $table = $this->table('aws');
        $table->addColumn('email', 'text', ['comment' => '注册邮箱'])
            ->addColumn('passwd', 'text', ['comment' => '登录密码'])
            ->addColumn('ak', 'text', ['comment' => 'access key'])
            ->addColumn('sk', 'text', ['comment' => 'secret key'])
            ->addColumn('mark', 'text', ['comment' => '备注'])
            ->addColumn('quota', 'text', ['comment' => '配额'])
            ->addColumn('disable', 'integer', ['comment' => '账户失效标识'])
            ->addColumn('user_id', 'integer', ['comment' => '归属用户'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->create();
    }
}
