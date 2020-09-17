<?php

namespace modules\api\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\UnauthorizedHttpException;
use common\models\MongoLoginForm as LoginForm;

class LoginController extends Controller
{
    public function actionIndex()
    {
        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post(), '') && $model->login()) {
            $response = new \stdClass();
            $response->access_token = Yii::$app->user->identity->access_token;
            return $response;
        }

        throw new UnauthorizedHttpException();
    }
}