<?php

namespace app\controller;

use app\model\Share as ShareModel;

class Share
{
    public function getShare()
    {
        try {
            $token = input('token/s');
            $share = ShareModel::where('token', $token)->find();
            // check
            if (!isset($share)) {
                throw new \Exception('Invalid link credentials.');
            }
            // update
            $share->is_use = 1;
            $share->save();
            // return
            $set = json_decode($share->content, true);
            return json_encode([
                'msg' => 'ok',
                'count' => count($set),
                'content' => $set,
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            return json([
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
