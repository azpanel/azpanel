<?php

use think\migration\db\Column;
use think\migration\Migrator;

class CustomWebsite extends Migrator
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
    public function up()
    {
        $rows = [
            [
                'id' => null,
                'item' => 'custom_text',
                'value' => '<a href="https://github.com/azpanel/azpanel">staff</a>',
                'class' => 'custom',
                'default_value' => '<a href="https://github.com/azpanel/azpanel">staff</a>',
                'type' => 'string',
            ],
            [
                'id' => null,
                'item' => 'custom_script',
                'value' => '<script></script>',
                'class' => 'custom',
                'default_value' => '<script></script>',
                'type' => 'string',
            ],
        ];

        $this->insert('config', $rows);
    }

    public function down()
    {
        $this->execute("DELETE FROM config WHERE config.item = 'custom_text'");
        $this->execute("DELETE FROM config WHERE config.item = 'custom_script'");
    }
}
