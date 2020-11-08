<?php

namespace modules\api\controllers;

use yii\rest\Controller;
use yii\filters\auth\HttpBearerAuth;
use common\models\MongoUser;
use yii\web\UnauthorizedHttpException;

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
        if (!$serviceId = \Yii::$app->request->headers->get('serviceId')) {
            throw new UnauthorizedHttpException();
        };

        $user = MongoUser::findOne(\Yii::$app->user->id);

        if (!$user->validateService($serviceId, $notify = false)) {
            throw new UnauthorizedHttpException();
        }
        
        return $user;
    }
}
