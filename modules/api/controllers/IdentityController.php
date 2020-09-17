<?php

namespace modules\api\controllers;

use yii\rest\Controller;
use yii\filters\auth\HttpBearerAuth;

class IdentityController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::className()
        ];
        
        return $behaviors;
    }
    
    public function actionIndex()
    {
        return \Yii::$app->user->identity;
    }
}
