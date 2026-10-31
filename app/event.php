<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

// 事件定义文件
use app\common\listener\live\RoomRankUpdateListener;
use app\common\listener\live\RoomUserEnterListener;
use app\common\listener\live\UserLevelUpListener;
use app\common\listener\message\BackendMessageListener;
use app\common\listener\schedule\OrderCrontabListener;
use app\common\listener\swoole\SwooleInitListener;
use app\common\listener\websocket\WebSocketCloseListener;
use app\common\listener\websocket\WebSocketMessageListener;
use app\common\listener\websocket\WebSocketOpenListener;
use app\common\listener\websocket\WebSocketWorkerStartListener;

return [
    'bind' => [
    ],

    'listen' => [
        'AppInit'               => [],
        'schedule'              => [
            OrderCrontabListener::class,
        ],
        'HttpRun'               => [
            \app\common\command\migrate\RegisterVersionedMigrateCommands::class,
        ],
        'HttpEnd'               => [],
        'LogLevel'              => [],
        'LogWrite'              => [],
        // Swoole 启动信息
        'swoole.init'            => [SwooleInitListener::class],
        // 直播间等级升级、贡献榜更新与用户进场广播
        'live.user.levelUp'      => [UserLevelUpListener::class],
        'live.room.rankUpdate'   => [RoomRankUpdateListener::class],
        'live.room.userEnter'    => [RoomUserEnterListener::class],
        // 后台消息通知
        'backend.message'         => [BackendMessageListener::class],
        // WebSocket 事件
        'swoole.websocket.Open'    => [WebSocketOpenListener::class],
        'swoole.websocket.Message' => [WebSocketMessageListener::class],
        'swoole.websocket.Close'   => [WebSocketCloseListener::class],
        'swoole.workerStart'       => [WebSocketWorkerStartListener::class],
    ],

    'subscribe' => [
    ],
];
