<?php

namespace app\controller;

use app\model\Task;

class UserTask
{
    public static function create($user_id, $name, $params = null, $task_uuid = null)
    {
        $task = new Task();
        $msg = [];
        $task->user_id = $user_id;
        $task->task_uuid = $task_uuid;
        $task->name = $name;
        $task->params = json_encode($params, JSON_UNESCAPED_UNICODE);
        $task->schedule = '0';
        $task->status = 'created';
        $task->current = '任务已创建';
        $info = [
            'time' => time(),
            'info' => '任务已创建',
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
        return Task::find($task_id);
    }

    public static function ajaxQuery($uuid)
    {
        $task = Task::where('user_id', session('user_id'))
            ->where('task_uuid', $uuid)
            ->find();

        $data = [
            'status' => $task->status ?? 'WaitForResponse',
            'schedule' => $task->schedule ?? 0,
            'current' => $task->current ?? '等待任务创建',
        ];

        return json($data);
    }

    public static function update($task_id, $progress, $hint)
    {
        $task = Task::find($task_id);
        $progress_rate = floor($progress * 100);

        $task->status = 'running';
        $task->schedule = $progress_rate;
        $task->current = $hint;
        $total = json_decode($task->total, true);
        $info = [
            'time' => time(),
            'rate' => $progress_rate,
            'info' => $hint,
        ];
        array_push($total, $info);
        $task->total = json_encode($total, JSON_UNESCAPED_UNICODE);
        $task->updated_at = time();
        $task->save();
    }

    public static function end($task_id, $crash, $error = null, $cancel = false)
    {
        $task = Task::find($task_id);
        $total = json_decode($task->total, true);

        if ($crash === true) {
            if ($cancel === false) {
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

        if (isset($error)) {
            if (is_array($error) && isset($error['msg'])) {
                $task->error = $error['msg'];
            }
        }

        $info = [
            'time' => time(),
            'info' => $task->current,
        ];
        array_push($total, $info);

        $task->total = json_encode($total, JSON_UNESCAPED_UNICODE);
        $task->updated_at = time();
        $task->total_time = time() - $task->created_at;
        $task->save();
    }
}
