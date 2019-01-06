<?php
namespace RLME\Models;

include_once '../vendor/autoload.php';
class User
{

  use Ramsey\Uuid\Uuid;
  use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
  use RLME\Utils\Sentry;

  public $username;
  public $email;
  public $password;
  public $userid;
  public $token;
  public $tier;
  public $userDetails;
  public $isLoggedIn;
  public $isAdmin;
  public $isBlocked;

  public function __construct(string $username,string $password = null)
  {
    include '../inc/development_db_password.inc.php';
    $dbconn = pg_connect("host=localhost port=5432 dbname=rlapi_devel user=rlapi_devel password=" . $dbPass); //Note, $dbPass is defined in development_db_password.inc.php
    $this->sentry_instance = new SentryInstance();
  }

  /* Functions Related to creating and deleting users */

  public function createUser(string $username, string $password, string $email)
  {
    // Sanitize
    $this->username = htmlspecialchars($username);
    $this->email = htmlspecialchars($email);
    // Encrypt Password
    $this->password = password_hash(htmlentities($password), PASSWORD_BCRYPT);
    unset($password); // We dont want to store the password in the code

    // Create User ID
    $this->userid = Uuid::uuid4();
    $this->userid = $this->userid->toString();

    // Add the user to DB
    $preparedStatement = pg_prepare($dbconn, "create_user", "INSERT INTO users ('id', 'username', 'password', 'email', 'tier', 'is_admin', 'is_blocked') VALUES ($1, $2, $3, $4, 'free', false, false)");
    $executePreparedStatement =  pg_execute($dbconn, "create_user", array($this->userid, $this->username, $this->password, $this->email));

    if(pg_result_status($executePreparedStatement) == 1 || pg_result_status($executePreparedStatement) == 6)
    {
      return 
        [
          'success' => true,
          'status' => 'created',
          'account' => [
            'id' => $this->userid,
            'username' => $this->username,
            'email' => $this->email
          ]
        ];
    }
    else
    {
      return
        [
          'success' => false,
          'message' => 'Something went horribly wrong while inserting the user into the database! Check the logs!'
        ];

      $this->sentry_instance->log_error('Something went horribly wrong while inserting the user into the database! Check the logs! Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

  public function deleteUser(mixed $id, string $email)
  {
    $this->userid = htmlspecialchars($id);
    $this->email = htmlspecialchars($email);

    $preparedStatement = pg_prepare($dbconn, "delete_user", "DELETE FROM users WHERE id = $1 AND email = $2");
    $executePreparedStatement = pg_execute($dbconn, "delete_user", array($this->userid, $this->email));

    $prepareStatementApiKeys = pg_prepare($dbconn, "delete_user_api_keys", "DELETE FROM tokens WHERE user_id = $1");
    $executePreparedStatementApiKeys = pg_execute($dbconn, "delete_user_api_keys", array($this->userid));

    if(pg_result_status($executePreparedStatement) == 1 || pg_result_status($executePreparedStatement) == 6 && pg_result_status($executePreparedStatementApiKeys) == 1 || pg_result_status($executePreparedStatementApiKeys) == 6)
    {
      return
        [
          'success' => true,
          'account' => [
            'deleted' => true
          ]
        ];
    }
    else
    {
      return
        [
          'success' => false,
          'message' => 'Something went horribly wrong while deleting the user from the database! Check the logs!'
        ];

      $this->sentry_instance->log_error('Something went horribly wrong while deleting the user from the database! Check the logs! Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }

   /* Other user-related functions */

  public function setUserTier(mixed $id, string $tier)
  {
    $this->id = $id;
    $preparedStatement = pg_prepare($dbconn, "update_tier", "UPDATE users SET tier = $1 WHERE id = $2");
    $executePreparedStatement =  pg_execute($dbconn, "update_tier", array($tier, $id));

    if($prepareStatement !== false && $executePreparedStatement !== false)
    {
      return
      [
        'success' => true,
        'account' => [
          'tier' => [
            'updated' => true
          ]
        ]
      ];
    }
    else
    {
      return
      [
        'success' => false,
        'account' => [
          'tier' => [
            'updated' => false
          ]
        ]
      ];
      $this->sentry_instance->log_error('Couldnt update the tier of user ' . $this->id .  ' Time: ' . gmdate("Y-m-d H:i:s", time()));
    }
  }
}

?>