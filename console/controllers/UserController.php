<?php

namespace console\controllers;

use yii\console\Controller;
use yii\base\InvalidArgumentException;
use common\models\MongoUser;
use frontend\models\MongoSignupForm;
use yii\console\ExitCode;
use yii\helpers\VarDumper;
use yii\base\Exception;

class UserController extends Controller
{
    public $id;
    public $username;
    public $password;
    public $email;
    public $status;
    
    // public function options($actionID)
    // {
    //     return [
    //         'id',
    //         'username',
    //         'password',
    //         'email',
    //         'status'
    //     ];
    // }
    
    public function optionAliases()
    {
        // return [
        //     'm' => 'message'
        // ];
    }
    
    public function actionIndex()
    {
        $users = MongoUser::find()->all();
        $this->stdout(VarDumper::dumpAsString(array_map(function($user){
            return $user->toArray();
        },  $users)));
        $this->stdout("\n");
        
        return ExitCode::OK;
    }

    public function actionCreate($username, $password, $email)
    {        
        $form = new MongoSignupForm();
        
        $form->load([
            'username' => $username,
            'password' => $password,
            'email' => $email
        ], '');
        
        if (!$form->validate()) {
            $this->stdout(VarDumper::dumpAsString($form->errors));
            $this->stdout("\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $user = new MongoUser();
        $user->username = $form->username;
        $user->email = $form->email;
        $user->setPassword($form->password);
        $user->generateAuthKey();
        $user->generateAccessToken();
        $user->generateEmailVerificationToken();
        
        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
        
        return ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionSetPassword($id, $password)
    {        
        $user = $this->findOne($id);

        $user->setPassword($password);

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionActivate($id)
    {        
        $user = $this->findOne($id);

        $user->status = MongoUser::STATUS_ACTIVE;

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    public function actionInactivate($id)
    {        
        $user = $this->findOne($id);

        $user->status = MongoUser::STATUS_INACTIVE;

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    public function actionDelete($id)
    {        
        $user = $this->findOne($id);

        $user->status = MongoUser::STATUS_DELETED;

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    private function findOne($id)
    {
        if (!$user = MongoUser::findOne($id)) {
            throw new Exception("User with id=<${id}> not found", 1);
        }

        return $user;
    }
}
