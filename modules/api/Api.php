<?php

namespace modules\api;

class Api extends \yii\base\Module
{
    public function init()
    {
        parent::init();

        \Yii::$app->user->enableSession = false;
    }
}