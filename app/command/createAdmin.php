<?php
declare(strict_types=1);

namespace app\command;

use app\controller\AzureList;
use app\controller\Tools;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class createAdmin extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('createAdmin')
            ->addOption('email', null, Option::VALUE_REQUIRED, 'login email')
            ->addOption('passwd', null, Option::VALUE_REQUIRED, 'login passwd')
            ->setDescription('Create administrator account');
    }

    protected function execute(Input $input, Output $output)
    {
        $email = trim($input->getOption('email'));
        $passwd = trim($input->getOption('passwd'));

        if ($email === '') {
            $output->writeln("<error>Please set a login email.</error>");
        }
        if ($passwd === '') {
            $output->writeln("<error>Please set a login password.</error>");
        }
        if (!Tools::emailCheck($email)) {
            $output->writeln("<error>E-mail format is incorrect.</error>");
        }

        $user = new User();
        $user->email = $email;
        $user->passwd = Tools::encryption($passwd);
        $user->status = 1;
        $user->is_admin = 1;
        $user->personalise = AzureList::defaultPersonalise();
        $user->created_at = time();
        $user->updated_at = time();
        $user->save();

        $output->writeln("<info>An administrator account has been created.</info>");
    }
}
