<?php

namespace console\controllers;

use yii\console\Controller;
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
    
    /**
     * Find all MongoUser
     *
     * @return common\models\MongoUser[]
     */
    public function actionIndex()
    {
        $users = MongoUser::find()->all();
        $this->stdout(VarDumper::dumpAsString(array_map(function($user){
            return $user->toArray();
        },  $users)));
        $this->stdout("\n");
        
        return ExitCode::OK;
    }

    /**
     * Create MongoUser
     *
     * @param string $username
     * @param string $password
     * @param string $email
     * @return yii\console\ExitCode::OK|yii\console\ExitCode::UNSPECIFIED_ERROR
     */
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

    /**
     * Set password
     *
     * @param string $id
     * @param string $password
     * @return yii\console\ExitCode::OK|yii\console\ExitCode::UNSPECIFIED_ERROR
     */
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

    /**
     * Set status MongoUser::STATUS_ACTIVE
     *
     * @param string $id
     * @return yii\console\ExitCode::OK
     */
    public function actionActivate($id)
    {        
        $user = $this->findOne($id);

        $user->activate();

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    /**
     * Set status MongoUser::STATUS_INACTIVE
     *
     * @param string $id
     * @return yii\console\ExitCode::OK
     */
    public function actionInactivate($id)
    {        
        $user = $this->findOne($id);

        $user->inactivate();

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    /**
     * Sets status MongoUser::STATUS_DELETED
     *
     * @param string $id
     * @return yii\console\ExitCode::OK
     */
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

    /**
     * 
     * Find MongoUser or throw Exception
     *
     * @param string $id
     * @return common\models\MongoUser
     * @throws Exception
     */
    private function findOne($id)
    {
        if (!$user = MongoUser::findOne($id)) {
            throw new Exception("User with id=<${id}> not found", 1);
        }

        return $user;
    }

    /**
     * Add service
     *
     * @param string $id
     * @param string $serviceId
     * @return yii\console\ExitCode::OK
     */
    public function actionAddService($id, $serviceId)
    {        
        $user = $this->findOne($id);

        $services = $user->services;

        if (array_search($serviceId, $services) !== false) {
            return;
        }

        $services[] = $serviceId;
        $user->services = $services;

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    /**
     * Delete service
     *
     * @param string $id
     * @param string $serviceId
     * @return yii\console\ExitCode::OK
     */
    public function actionDeleteService($id, $serviceId)
    {        
        $user = $this->findOne($id);

        $services = $user->services;
        $index = array_search($serviceId, $services);

        if ($index === false) {
            return;
        }

        unset($services[$index]);
        $user->services = $services;

        if ($user->save()) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }

    /**
     * Has service
     *
     * @param string $id
     * @param string $serviceId
     * @return yii\console\ExitCode::OK
     */
    public function actionValidateService($id, $serviceId)
    {        
        $user = $this->findOne($id);

        if (!$user) {
            throw new Exception("User not found", 1);
        }

        $user->validateService($serviceId);

        if ($user) {
            $this->stdout(VarDumper::dumpAsString($user->toArray()));
            $this->stdout("\n");
            return ExitCode::OK;
        }
    }
}