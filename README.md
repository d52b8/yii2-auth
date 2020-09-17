# yii2-auth

## Install

``` sh
composer require --prefer-dist yiisoft/yii2-mongodb
```

## Config

`common/config/main-local.php`

``` php

// Mongodb
'mongodb' => [
    'class' => '\yii\mongodb\Connection',
    'dsn' => 'mongodb://localhost:27017/auth',
],

```

## User Console Controller

``` sh

./yii user
./yii user/create 'username' 'password' 'email'
./yii user/set-password 'id' 'password'
./yii user/delete 'id'
./yii user/activate 'id'

```
