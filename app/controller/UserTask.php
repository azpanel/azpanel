<?php
namespace app\controller;

use app\model\Task;

class UserTask
{
    public static function create($user_id, $name, $params = null)
    {
        $task = new Task;
        $msg = [];
        $task->user_id     = $user_id;
        $task->name        = $name;
        $task->params      = $params;
        $task->schedule    = '0';
        $task->status      = 'created';
        $task->current     = '任务已创建';
        $info = [
            'time' => time(),
            'info' => '任务已创建'
        ];
        array_push($msg, $info);
        $task->total = json_encode($msg, JSON_UNESCAPED_UNICODE);
        $task->created_at = time();
        $task->updated_at = time();
        $task->save();

        return $task->id;
    }

    public static function query($task_id)
    {
        $task = Task::find($task_id);
        return $task;
    }

    public static function ajaxQuery()
    {
        $task = Task::where('user_id', session('user_id'))
        ->order('id', 'desc')
        ->find();

        $data = [
            'status' => $task->status,
            'schedule' => $task->schedule,
            'current' => $task->current
        ];
        return json($data);
    }

    public static function update($task_id, $progress, $hint)
    {
        $task = Task::find($task_id);
        $progress_rate = floor($progress * 100);

        $task->status   = 'running';
        $task->schedule = $progress_rate;
        $task->current  = $hint;
        $total = json_decode($task->total, true);
        $info = [
            'time' => time(),
            'rate' => $progress_rate,
            'info' => $hint
        ];
        array_push($total, $info);
        $task->total      = json_encode($total, JSON_UNESCAPED_UNICODE);
        $task->updated_at = time();
        $task->save();
    }

    public static function end($task_id, $crash, $error = null, $cancel = false)
    {
        $task = Task::find($task_id);
        $total = json_decode($task->total, true);

        if ($crash == true) {
            if ($cancel == false) {
                $task->status = 'terminated';
                $task->current = '任务出错';
            } else {
                $task->status = 'cancelled';
                $task->current = '任务已取消';
            }
        } else {
            $task->status = 'completed';
            $task->schedule = '100';
            $task->current = '任务已完成';
        }

        if ($error != null) {
            $task->error = $error;
        }

        $info = [
            'time' => time(),
            'info' => $task->current
        ];
        array_push($total, $info);

        $task->total      = json_encode($total, JSON_UNESCAPED_UNICODE);
        $task->updated_at = time();
        $task->total_time = time() - ($task->created_at);
        $task->save();
    }
}
