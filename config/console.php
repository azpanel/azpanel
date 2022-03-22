<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    'commands' => [
        'tools' => 'app\command\Tools',
        'createAdmin' => 'app\command\createAdmin',
        'closeTimeoutTask' => 'app\command\closeTimeoutTask',
        'trafficControlStop' => 'app\command\trafficControlStop',
        'trafficControlStart' => 'app\command\trafficControlStart',
        'autoRefreshAccount' => 'app\command\autoRefreshAccount',
    ]
];
