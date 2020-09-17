<?php

namespace modules\api\controllers;

use yii\rest\Controller;

class ErrorController extends Controller
{   
    public function actionIndex()
    {
        return \Yii::$app->errorHandler->exception;
    }
}

