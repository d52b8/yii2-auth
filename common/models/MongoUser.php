<?php
namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\mongodb\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * MongoUser model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $verification_token
 * @property string $email
 * @property string $auth_key
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $password write-only password
 * @property string $access_token
 * @property array $services
 */
class MongoUser extends ActiveRecord implements IdentityInterface
{
    const EVENT_LOGIN_ATTEMPT = 'eventLoginAttempt';
    const EVENT_LOGIN_ATTEMPT_SUCCESS = 'eventLoginAttemptSuccess';
    const EVENT_LOGIN_ATTEMPT_FAIL = 'eventLoginAttemptFail';
    const EVENT_USER_INACTIVE = 'eventUserInactivate';
    const EVENT_USER_ACTIVATE = 'eventUsernActivate';
    const EVENT_VALIDATE_SERVICE = 'eventValidateService';
    const EVENT_VALIDATE_SERVICE_SUCCESS = 'eventValidateServiceSuccess';
    const EVENT_VALIDATE_SERVICE_FAIL = 'eventValidateServiceFail';
    
    const STATUS_DELETED = 0;
    const STATUS_INACTIVE = 9;
    const STATUS_ACTIVE = 10;

    const MAX_LOGIN_ATTEMPT = 5;
    const MAX_VALIDATE_SERVICE = 5;

    const SERVICE_FULL_ACCESS = '*';

    private $_serviceId;
    private $_notify;

    /**
     * {@inheritdoc}
     */
    public static function CollectionName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return [
            '_id',
            'username',
            'auth_key',
            'password_hash',
            'password_reset_token',
            'email',
            'status',
            'created_at',
            'updated_at',
            'verification_token',
            'access_token',
            'login_attempt',
            'services'
        ];
    }

    public function init()
    {
       parent::init();

       $this->on(self::EVENT_LOGIN_ATTEMPT, [$this, 'eventLoginAttempt']);
       $this->on(self::EVENT_LOGIN_ATTEMPT_SUCCESS, [$this, 'eventLoginAttemptSuccess']);
       $this->on(self::EVENT_LOGIN_ATTEMPT_FAIL, [$this, 'eventLoginAttemptFail']);
       $this->on(self::EVENT_USER_INACTIVE, [$this, 'eventUserInactivate']);
       $this->on(self::EVENT_USER_ACTIVATE, [$this, 'eventUserActivate']);
       $this->on(self::EVENT_VALIDATE_SERVICE, [$this, 'eventValidateService']);
       $this->on(self::EVENT_VALIDATE_SERVICE_SUCCESS, [$this, 'eventValidateServiceSuccess']);
       $this->on(self::EVENT_VALIDATE_SERVICE_FAIL, [$this, 'eventValidateServiceFail']);
    }
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'value' => function() { 
                    return new \MongoDB\BSON\UTCDateTime(
                        \Yii::$app->formatter->asTimestamp(time() * 1000)
                    ); 
                },
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
                ]
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED]],
            ['services', 'default', 'value' => []],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function find()
    {
        return new MongoUserQuery(get_called_class());
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        return static::findOne(['_id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['access_token' => $token, 'status' => self::STATUS_ACTIVE]);
        // throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken($token) {
        return static::findOne([
            'verification_token' => $token,
            'status' => self::STATUS_INACTIVE
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Generates new token for email verification
     */
    public function generateEmailVerificationToken()
    {
        $this->verification_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

     /**
     * Generates Access Token
     */
    public function generateAccessToken()
    {
        $this->access_token = Yii::$app->security->generateRandomString();
    }

    /**
     * Increment login counter
     *
     * @return void
     */
    private function loginAttempt()
    {
        $this->login_attempt++;
        $this->trigger(self::EVENT_LOGIN_ATTEMPT);
    }

    /**
     * Increment login counter
     *
     * @return void
     */
    private function loginFail()
    {
        $this->trigger(self::EVENT_LOGIN_ATTEMPT_FAIL);

        if ($this->login_attempt >= self::MAX_LOGIN_ATTEMPT) {
            $this->inactivate();
        }
    }

    /**
     * Increment login counter
     *
     * @return void
     */
    private function loginSuccess()
    {
        $this->trigger(self::EVENT_LOGIN_ATTEMPT_SUCCESS);
        $this->login_attempt = 0; 
    }

    /**
     * Set inactivate status
     *
     * @return void
     */
    public function inactivate()
    {
        $this->status = self::STATUS_INACTIVE;
        $this->trigger(self::EVENT_USER_INACTIVE);
    }

    /**
     * Set activate status
     *
     * @return void
     */
    public function activate()
    {
        $this->status = self::STATUS_ACTIVE;
        $this->login_attempt = 0;
        $this->trigger(self::EVENT_USER_ACTIVATE);
    }

    /**
     * Validate password
     * Audit login attempt (increment attempt if wrong password)
     * Inactivate if needed
     *
     * @return boolean
     */
    public function validatePasswordWithAudit($password)
    {
        $this->loginAttempt();
        
        if ($this->validatePassword($password)) {
            $this->loginSuccess();      
            $this->save();
            return true;
        }

        $this->loginFail();
        $this->save();

        return false;
    }

     /**
     * Validate password
     * Audit login attempt (increment attempt if wrong password)
     * Inactivate if needed
     *
     * @return boolean
     */
    public function validateService($serviceId, $notify = true)
    {
        $this->_serviceId = $serviceId;
        $this->_notify = $notify;

        $this->trigger(self::EVENT_VALIDATE_SERVICE);
        
        $services = $this->services;
        $serviceIndex = array_search($this->_serviceId, $services);
        $fullAccessIndex = array_search(self::SERVICE_FULL_ACCESS, $services);

        if ($serviceIndex === false && $fullAccessIndex === false) {
            $this->trigger(self::EVENT_VALIDATE_SERVICE_FAIL);
            return false;
        }

        $this->trigger(self::EVENT_VALIDATE_SERVICE_SUCCESS);
        return true;
    }

    public function eventLoginAttempt($event)
    {
        $message = "Учетная запись {$event->sender->username} попытка авторизации";
        $response = (\Yii::$container->get('bot'))->sendMessage($message);
    }

    public function eventLoginAttemptSuccess($event)
    {
        $message = "Учетная запись {$event->sender->username} пароль принят";
        $response = (\Yii::$container->get('bot'))->sendMessage($message);
    }

    public function eventLoginAttemptFail($event)
    {
        $message = "Учетная запись {$event->sender->username} пароль не принят {$event->sender->login_attempt}";
        $response = (\Yii::$container->get('bot'))->sendMessage($message);
    }

    public function eventUserInactivate($event)
    {
        $message = "Учетная запись {$event->sender->username} заблокирована";
        $response = (\Yii::$container->get('bot'))->sendMessage($message);
    }

    public function eventUserActivate($event)
    {
        $message = "Учетная запись {$event->sender->username} активирована";
        $response = (\Yii::$container->get('bot'))->sendMessage($message);
    }

    public function eventValidateService($event)
    {
        if ($this->_notify) {                    
            $message = "Учетная запись {$event->sender->username} проверка разрешения доступа к сервису {$event->sender->_serviceId}";
            $response = (\Yii::$container->get('bot'))->sendMessage($message);
        }
    }

    public function eventValidateServiceSuccess($event)
    {
        if ($this->_notify) {  
            $message = "Учетная запись {$event->sender->username} доступ к сервису разрешен";
            $response = (\Yii::$container->get('bot'))->sendMessage($message);
        }
    }

    public function eventValidateServiceFail($event)
    {
        if ($this->_notify) { 
            $message = "Учетная запись {$event->sender->username} доступ к сервису запрещен";
            $response = (\Yii::$container->get('bot'))->sendMessage($message);
        }
    }
}


class MongoUserQuery extends \yii\mongodb\ActiveQuery
{
    public function hasService($serviceId)
    {
        return $this->andWhere(['$or' => [
            ['services' => $serviceId],
            ['services' => '*']
        ]]);
    }
}
