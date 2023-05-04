<?php

namespace app\controller;

use app\controller\Tools;
use app\model\Ann;
use think\facade\View;

class AdminAnn extends AdminBase
{
    public function index()
    {
        $anns = Ann::order('id', 'desc')->select();

        View::assign('anns', $anns);
        return View::fetch('../app/view/admin/ann/index.html');
    }

    public function create()
    {
        return View::fetch('../app/view/admin/ann/create.html');
    }

    public function save()
    {
        $title = input('title/s');
        $status = input('status');
        $content = input('content/s');

        if ($title === '' || $content === '') {
            return json(Tools::msg('0', '添加失败', '标题或内容不能为空'));
        }

        $ann = new Ann();
        $ann->title = $title;
        $ann->content = $content;
        $ann->status = $status;
        $ann->created_at = time();
        $ann->updated_at = time();
        $ann->save();

        return json(Tools::msg('1', '添加结果', '已添加此条公告'));
    }

    public function edit($id)
    {
        $ann = Ann::find($id);

        View::assign('ann', $ann);
        return View::fetch('../app/view/admin/ann/edit.html');
    }

    public function update($id)
    {
        $title = input('title');
        $status = input('status');
        $content = input('content');

        if ($title === '' || $content === '') {
            return json(Tools::msg('0', '更新失败', '公告标题或内容不能为空'));
        }

        $ann = Ann::find($id);
        $ann->title = $title;
        $ann->content = $content;
        $ann->status = $status;
        $ann->updated_at = time();
        $ann->save();

        return json(Tools::msg('1', '更新结果', '已更新此条公告内容'));
    }

    public function delete($id)
    {
        Ann::destroy($id);

        return json(Tools::msg('1', '删除结果', '已删除此条公告'));
    }
}
