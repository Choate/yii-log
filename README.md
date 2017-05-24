# Yii2-log

扩展日志格式

# 支持格式

1. json

# 安装
基于composer安装

`php composer.phar require choate/yii-log`


# 使用

```php
'log'        => [
    'targets'       => [
        'trace' => [
            'class'   => 'choate\yii\log\JsonFileTarget',
            'levels'  => 79,
            'logFile' => '@runtime/logs/console.log',
        ],
    ],
],
```

# Targets

## JsonFileTarget

### 配置说明

1. `logTag` 日志唯一标识符，用于追踪一个请求所产生的日志

### 输出日志说明

```
{
    ip: "-",  // 请求IP
    user_id: "-",  // 请求用户
    session_id: "-",  // 请求用户SESSION ID
    request_id: "59252e208c8ac",  // 请求唯一标识符用于追踪日志
    application: "default", // 请求应用
    route: "default/index",  // 请求路由
    status_code: 0,  // 响应状态码
    request_time: "2017-05-24 14:54:24",  // 请求时间
    start_time: 1495608864.568761, 
    end_time: 1495608864.576908, 
    duration: "8 ms",  // 请求耗时
    SERVER: {},  // PHP $_SERVER
    COOKIE: {}, // PHP $_COOKIE
    POST: {}, // PHP $_POST
    GET: {}, // PHP $_GET
    FILES: {}, // PHP $_FILES
    operation: { // 请求操作路由日志
        0: {
            level: "info", 
            category: "application", 
            timestamp: "2017-05-24 14:54:24", 
            text: "成功", 
            traces: [ ]
        }, 
        1: {
            level: "info", 
            category: "application", 
            timestamp: "2017-05-24 14:54:24", 
            text: "成功", 
            traces: [ ]
        }
    }
}
```

