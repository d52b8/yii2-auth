<?php

namespace modules\api\controllers;

use yii\rest\Controller;
use yii\filters\auth\HttpBearerAuth;
use common\models\MongoUser;
use yii\web\UnauthorizedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;

class ProfileController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::className()
        ];
        
        return $behaviors;
    }
    
    public function actionIndex($id)
    {
        $user = MongoUser::findOne(["_id" => $id]);

        if (!$user) {
            throw new NotFoundHttpException();
        }
        
        return $user->getProfile();
    }
}
