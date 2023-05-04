<?php

use think\migration\db\Column;
use think\migration\Migrator;

class TrafficControlRule extends Migrator
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
        $table = $this->table('traffic_control_rule');
        $table->addColumn('user_id', 'integer', ['comment' => '创建用户'])
            ->addColumn('name', 'text', ['comment' => '规则名称'])
            ->addColumn('switch', 'integer', ['comment' => '规则开关'])
            ->addColumn('index', 'text', ['comment' => '检索的指标'])
            ->addColumn('interval', 'integer', ['comment' => '检索的时间范围'])
            ->addColumn('limit', 'integer', ['comment' => '指标限制值'])
            ->addColumn('time', 'integer', ['comment' => '限制时长'])
            ->addColumn('execute_push', 'integer', ['comment' => '触发时是否通知'])
            ->addColumn('recover_push', 'integer', ['comment' => '恢复后是否通知'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->addColumn('updated_at', 'integer', ['comment' => '更新时间'])
            ->create();

        $table = $this->table('traffic_control_task');
        $table->addColumn('user_id', 'integer', ['comment' => '创建用户'])
            ->addColumn('rule_id', 'integer', ['comment' => '流量控制规则'])
            ->addColumn('vm_id', 'text', ['comment' => '关联虚拟机'])
            ->addColumn('status', 'text', ['comment' => '执行状态'])
            ->addColumn('recover_push', 'integer', ['comment' => '恢复后是否通知'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->addColumn('execute_at', 'integer', ['comment' => '执行时间'])
            ->create();

        $table = $this->table('traffic_control_log');
        $table->addColumn('user_id', 'integer', ['comment' => '创建用户'])
            ->addColumn('rule_id', 'integer', ['comment' => '流量控制规则'])
            ->addColumn('vm_id', 'text', ['comment' => '关联虚拟机'])
            ->addColumn('action', 'text', ['comment' => '执行操作'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->create();
    }
}
