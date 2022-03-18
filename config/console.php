<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    'commands' => [
        'tools' => 'app\command\Tools',
        'createAdmin' => 'app\command\createAdmin',
        'closeTimeoutTask' => 'app\command\closeTimeoutTask',
        'autoRefreshAccount' => 'app\command\autoRefreshAccount',
    ]
];
