<?php

namespace modules\api\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\UnauthorizedHttpException;
use common\models\MongoLoginForm as LoginForm;
use common\models\MongoUser;

class LoginController extends Controller
{
    public function actionIndex()
    {
        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post(), '') && $model->login()) {
            
            $user = MongoUser::findOne(\Yii::$app->user->id);

            if (!$user->validateService($model->serviceId)) {
                throw new UnauthorizedHttpException();
            }

            $response = new \stdClass();
            $response->access_token = $user->access_token;

            return $response;
        }

        throw new UnauthorizedHttpException();
    }
}