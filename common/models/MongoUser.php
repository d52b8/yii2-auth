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
 */
class MongoUser extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 0;
    const STATUS_INACTIVE = 9;
    const STATUS_ACTIVE = 10;

    const MAX_LOGIN_ATTEMPT = 5;

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
            'login_attempt'
        ];
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
        ];
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
    }

    /**
     * Set inactivate status
     *
     * @return void
     */
    public function inactivate()
    {
        $this->status = self::STATUS_INACTIVE;
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
        if ($this->validatePassword($password)) {
            $message = "Учетная запись {$this->username} Успешная авторизация";
            $response = (\Yii::$container->get('bot'))->sendMessage($message);
            return true;
        }

        $this->loginAttempt();
        $message = "Учетная запись {$this->username} Попытка ввода неверного пароля {$this->login_attempt}";
        $response = (\Yii::$container->get('bot'))->sendMessage($message);

        if ($this->login_attempt >= self::MAX_LOGIN_ATTEMPT) {
            $this->inactivate();
            $message = "Учетная запись {$this->username} заблокирована. Превышен лимит попыток неверного ввода пароля";
            $response = \Yii::$container->get('bot')->sendMessage($message);
        }

        $this->save();

        return false;
    }
}