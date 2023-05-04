<?php
declare(strict_types=1);

namespace app\command;

use app\model\Task;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class closeTimeoutTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('closeTimeoutTask')
            ->setDescription('Close the task that timed out due to exception');
    }

    protected function execute(Input $input, Output $output)
    {
        $count = 0;
        $time_limit = time() - 3600;
        $tasks = Task::where('status', 'running')
            ->where('updated_at', '<', $time_limit)
            ->select();

        foreach ($tasks as $task) {
            ++$count;
            $task->status = 'closed';
            $info = [
                'time' => time(),
                'info' => '任务被系统标记为关闭',
            ];
            $total = json_decode($task->total, true);
            array_push($total, $info);
            $task->total = json_encode($total, JSON_UNESCAPED_UNICODE);
            $task->total_time = $task->updated_at - $task->created_at;
            $task->save();
        }

        $output->writeln("<info>A total of {$count} timeout tasks are closed this time.</info>");
    }
}
