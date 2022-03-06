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

Route::get('/',                'Auth/index');
Route::get('/login',           'Auth/index');
Route::post('/login',          'Auth/login');
Route::post('/logout',         'Auth/logout');
// 注册账户
Route::get('/register',        'Auth/registerIndex');
Route::post('/register/code',  'Auth/registerCode');
Route::post('/register',       'Auth/publicRegister');
// 重置密码
Route::get('/forget',        'Auth/forgetIndex');
Route::post('/forget/code',  'Auth/forgetCode');
Route::post('/forget',       'Auth/resetPassword');

Route::get('/user',            'UserDashboard/index');
Route::get('/user/login',      'UserDashboard/loginLog');

Route::get('/user/profile',                       'UserDashboard/profile');
Route::put('/user/profile/notify',                'UserDashboard/saveNotify');
Route::put('/user/profile/passwd',                'UserDashboard/savePasswd');
Route::put('/user/profile/personalise',           'UserDashboard/savePersonalise');

Route::get('/user/license',                       'UserDashboard/license');
Route::get('/user/docs',                          'UserDashboard/docs');

// Azure 账户
Route::resource('/user/azure',                    'UserAzure');
Route::post('/user/azure/refresh',                'UserAzure/refreshAllAzureSubscriptionStatus');
Route::post('/user/azure/refresh/:id',            'UserAzure/refreshAzureSubscriptionStatus');
Route::post('/user/azure/update/:id',             'UserAzure/updateAzureSubscriptionResources');
Route::delete('/user/azure/disabled',             'UserAzure/deleteAzureDisabledSubscription');
Route::delete('/user/azure/resources',            'UserAzure/deleteResourceGroup');
Route::get('/user/azure/resources/:id',           'UserAzure/readResourceGroupsList');
Route::get('/user/azure/resources/:id/:name',     'UserAzure/readResourceGroup');

// Azure 服务器
Route::resource('/user/server/azure',             'UserAzureServer');
Route::patch('/user/server/azure/:action/:uuid',  'UserAzureServer/status');
Route::post('/user/server/azure/remark/:uuid',    'UserAzureServer/remark');
Route::post('/user/server/azure/refresh/:uuid',   'UserAzureServer/refresh');
Route::post('/user/server/azure/change/:uuid',    'UserAzureServer/change');
Route::post('/user/server/azure/check/:ipv4',     'UserAzureServer/check');
Route::delete('/user/server/azure/remove/:uuid',  'UserAzureServer/delete');
Route::delete('/user/server/azure/destroy/:uuid', 'UserAzureServer/destroy');
Route::get('/user/server/azure/:id/chart/[:gap]',  'UserAzureServer/chart');

// 管理员
Route::get('/admin',                              'AdminDashboard/index');
Route::resource('/admin/ann',                     'AdminAnn');
Route::resource('/admin/user',                    'AdminUser');
Route::get('/admin/user/assets/:id',              'AdminUser/userAssets');
Route::patch('/admin/user/remark/:id',            'AdminUser/remark');

Route::get('/admin/setting',                      'AdminSetting/baseIndex');
Route::put('/admin/setting',                      'AdminSetting/baseSave');
// 邮件
Route::get('/admin/setting/email',                'AdminSetting/emailIndex');
Route::put('/admin/setting/email',                'AdminSetting/emailSave');
Route::post('/admin/setting/email/test',          'AdminSetting/emailPushTest');
// Telegram
Route::get('/admin/setting/telegram',             'AdminSetting/telegramIndex');
Route::put('/admin/setting/telegram',             'AdminSetting/telegramSave');
Route::post('/admin/setting/telegram/test',       'AdminSetting/telegramPushTest');

Route::get('/user/progress',   'UserTask/ajaxQuery')->json();
