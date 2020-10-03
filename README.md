# yii2-auth

## Install

``` sh
composer require --prefer-dist yiisoft/yii2-mongodb
```

## Config

`common/config/main-local.php`

``` php

// Mongodb
'components' => [
    //...
    'mongodb' => [
        'class' => '\yii\mongodb\Connection',
        'dsn' => 'mongodb://localhost:27017/auth',
    ],
    //...
],
'container' => [
    'definitions' => [
        'bot' => function () {
            return new \d52b8\telegram\Bot([
                'token' => '<TOKEN>',
                'chat_id' => '<CHAT_ID>'
            ]);
        }
    ],
]

```

## User Console Controller

``` sh

./yii user
./yii user/create 'username' 'password' 'email'
./yii user/set-password 'id' 'password'
./yii user/activate 'id'
./yii user/inactivate 'id'
./yii user/delete 'id'

```
