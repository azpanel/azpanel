<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::pattern([
    'id'    => '\d+',
    'name'  => '[\w\-]+',
    'uuid'  => '[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}',
]);

// 登入登出
Route::get('/',                'Auth/index');
Route::get('/login',           'Auth/index');
Route::post('/login',          'Auth/login');
Route::post('/logout',         'Auth/logout');

// 注册账户
Route::get('/register',        'Auth/registerIndex');
Route::post('/register/code',  'Auth/registerCode');
Route::post('/register',       'Auth/publicRegister');

// 重置密码
Route::get('/forget',          'Auth/forgetIndex');
Route::post('/forget/code',    'Auth/forgetCode');
Route::post('/forget',         'Auth/resetPassword');

// 其他功能
Route::get('/user',            'UserDashboard/index');
Route::get('/user/login',      'UserDashboard/loginLog');
Route::get('/user/share',      'UserDashboard/shareList');
Route::get('/user/recycle',    'UserDashboard/recycle');

// 个人资料
Route::get('/user/profile',                       'UserDashboard/profile');
Route::get('/user/profile/sshkey',                'UserDashboard/createSshKey');
Route::put('/user/profile/sshkey',                'UserDashboard/resetSshKey');
Route::put('/user/profile/notify',                'UserDashboard/saveNotify');
Route::put('/user/profile/passwd',                'UserDashboard/savePasswd');
Route::put('/user/profile/refresh',               'UserDashboard/saveRefresh');
Route::put('/user/profile/personalise',           'UserDashboard/savePersonalise');

// 关于项目
Route::get('/user/license',                       'UserDashboard/license');
Route::get('/user/docs',                          'UserDashboard/docs');

// Azure 账户
Route::resource('/user/azure',                    'UserAzure');
Route::post('/user/azure/quota/:id',              'UserAzure/queryAccountQuota');
Route::post('/user/azure/cost/:id',               'UserAzure/estimatedCost');
Route::post('/user/azure/refresh',                'UserAzure/refreshAllAzureSubscriptionStatus');
Route::put('/user/azure/share',                   'UserAzure/shareAccount');
Route::post('/user/azure/share',                  'UserAzure/processShare');
Route::post('/user/azure/search',                 'UserAzure/searchAccount');
Route::post('/user/azure/refresh/:id',            'UserAzure/refreshAzureSubscriptionStatus');
Route::post('/user/azure/update/:id',             'UserAzure/updateAzureSubscriptionResources');
Route::delete('/user/azure/disabled',             'UserAzure/deleteAzureDisabledSubscription');
Route::delete('/user/azure/resources',            'UserAzure/deleteResourceGroup');
Route::get('/user/azure/resources/:id',           'UserAzure/readResourceGroupsList');
Route::get('/user/azure/resources/:id/:name',     'UserAzure/readResourceGroup');

// Azure 服务器规则
Route::resource('/user/server/azure/rule',        'UserAzureServerRule');
Route::get('/user/server/azure/rule/log',         'UserAzureServerRule/log');

// Aws 账户
Route::resource('/user/aws',                      'UserAws');
Route::post('/user/aws/search',                   'UserAws/searchAccount');

// Aws 服务器
Route::resource('/user/server/aws',               'UserAwsServer');

// Azure 服务器
Route::resource('/user/server/azure',             'UserAzureServer');
Route::post('/user/server/azure/search',          'UserAzureServer/search');
Route::post('/user/server/azure/available',       'UserAzureServer/available');
Route::post('/user/server/azure/price',           'UserAzureServer/price');
Route::patch('/user/server/azure/:action/:uuid',  'UserAzureServer/status');
Route::put('/user/server/azure/resize/:uuid',     'UserAzureServer/resize');
Route::put('/user/server/azure/redisk/:uuid',     'UserAzureServer/redisk');
Route::put('/user/server/azure/rule/:uuid',       'UserAzureServer/update');
Route::post('/user/server/azure/remark/:uuid',    'UserAzureServer/remark');
Route::post('/user/server/azure/refresh/:uuid',   'UserAzureServer/refresh');
Route::post('/user/server/azure/change/:uuid',    'UserAzureServer/change');
Route::post('/user/server/azure/check/:ipv4',     'UserAzureServer/check');
Route::post('/user/server/azure/sync/:uuid',      'UserAzureServer/sync');
Route::delete('/user/server/azure/remove/:uuid',  'UserAzureServer/delete');
Route::delete('/user/server/azure/destroy/:uuid', 'UserAzureServer/destroy');
Route::get('/user/server/azure/:id/chart/[:gap]', 'UserAzureServer/chart');

// 管理员
Route::get('/admin',                              'AdminDashboard/index');
Route::resource('/admin/ann',                     'AdminAnn');
Route::resource('/admin/user',                    'AdminUser');
Route::get('/admin/user/report',                  'AdminUser/userReport');
Route::get('/admin/user/assets/:id',              'AdminUser/userAssets');
Route::patch('/admin/user/remark/:id',            'AdminUser/remark');

// 设置
Route::get('/admin/setting',                      'AdminSetting/baseIndex');
Route::put('/admin/setting',                      'AdminSetting/baseSave');

// 解析
Route::get('/admin/setting/resolv',               'AdminSetting/resolvIndex');
Route::put('/admin/setting/resolv',               'AdminSetting/resolvSave');

// 邮件
Route::get('/admin/setting/email',                'AdminSetting/emailIndex');
Route::put('/admin/setting/email',                'AdminSetting/emailSave');
Route::post('/admin/setting/email/test',          'AdminSetting/emailPushTest');

// 电报
Route::get('/admin/setting/telegram',             'AdminSetting/telegramIndex');
Route::put('/admin/setting/telegram',             'AdminSetting/telegramSave');
Route::post('/admin/setting/telegram/test',       'AdminSetting/telegramPushTest');

// 网站
Route::get('/admin/setting/custom',               'AdminSetting/customIndex');
Route::put('/admin/setting/custom',               'AdminSetting/customSave');

// 日志
Route::get('/admin/log/login',                    'AdminLog/login');
Route::get('/admin/log/verify',                   'AdminLog/verify');
Route::get('/admin/log/resize',                   'AdminLog/resize');
Route::get('/admin/log/traffic',                  'AdminLog/traffic');
Route::get('/admin/log/task',                     'AdminLog/task');
Route::get('/admin/log/task/:id',                 'AdminLog/taskDetails');

// 其他
Route::get('/share',                              'Share/getShare');
Route::get('/user/progress/:uuid',                'UserTask/ajaxQuery')->json();

// 代理测试
Route::post('/proxy/test',                        'ProxyController/test');
