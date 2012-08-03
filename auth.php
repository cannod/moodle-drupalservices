<?php
/**
 * @author Dave Cannon (based on Arsham Skrenes) 
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle multiauth
 *
 * Authentication Plugin: Drupal Services Single Sign-on
 *
 * This module will look for a Drupal cookie that represents a valid,
 * authenticated session, and will use it to create an authenticated Moodle
 * session for the same user. The Drupal user will be synchronized with the
 * corresponding user in Moodle. If the user does not yet exist in Moodle, it
 * will be created.
 */

// This must be accessed from a Moodle page only!
if (!defined('MOODLE_INTERNAL')) {
   die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir.'/authlib.php');
require_once($CFG->dirroot.'/auth/drupalservices/REST-API.php');

// Drupal SSO authentication plugin.
class auth_plugin_drupalservices extends auth_plugin_base {

   // Constructor
   function auth_plugin_drupalservices() {
      $this->authtype = 'drupalservices';
      $this->config = get_config('auth/drupalservices');
   }
   
   // This plugin is for SSO only; Drupal handles the login
   function user_login($username, $password) { return false; }

   // Function to enable SSO (it runs before user_login() is called)
   // If a valid Drupal session is not found, the user will be forced to the
   // login page where some other plugin will have to authenticate the user
   function loginpage_hook() {
      global $CFG, $USER, $SESSION, $DB;
      // Check if we have a Drupal session.

      $base_url = $this->config->hostname;
      $endpoint = $this->config->endpoint;

      $drupalsession = $this->get_drupal_session($base_url);

      if (empty($drupalsession)) {
        // redirect to drupal login page with destination 
        if (isset($SESSION->wantsurl) and
         (strpos($SESSION->wantsurl, $CFG->wwwroot) == 0)) {
            // the URL is set and within Moodle's environment
            $urltogo = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
            //parse_url($urltogo)
            // Apache did not like // sp remove leading slash
            //$path = ltrim($urltogo, $CFG->wwwroot);
            //$path = ltrim($path,'/' );
            $path = ltrim(parse_url($urltogo, PHP_URL_PATH), '/'); 
            $args = parse_url($urltogo, PHP_URL_QUERY);
            if ($args) {
              $args = '?' . $args;
            }
            // FIX so not hard coded.
            redirect($base_url . '/user/login?destination=' . $path . $args);
         }
         return; // just send user to login page 
      }

      // Verify the authenticity of the Drupal session ID
      // Create JSON cookie used to connect to drupal services.
      // So we connect to system/connect and we should get back a valid drupal user.
      $session_name = $drupalsession['session_name'];
      $session_id = $drupalsession['session_id'];

      $SESSION->drupal_session_name = $session_name;
      $SESSION->drupal_session_id = $session_id;

      $apiObj = new RemoteAPI( $base_url, $endpoint, 1, $session_name, $session_id );

      $ret = $apiObj->Connect();

      if (is_null($ret)) {
        //should we just return?
        if (isloggedin() && !isguestuser()) {
               // the user is logged-off of Drupal but still logged-in on Moodle
               // so we must now log-off the user from Moodle...
               require_logout();
        }
        return;
      }

      $uid = $ret->user->uid;

      if ($uid < 1) { //No anon 
        return;
      }

      //$drupal_user = $this->drupalservices_users($uid, $services_url, $session_cookie);
      $drupal_user = $apiObj->Get('user', $uid);  // <- Get user 

      if (is_null($drupal_user) || empty($drupal_user)) {
        
        //should we just return?
        if (isloggedin() && !isguestuser()) {
               // the user is logged-off of Drupal but still logged-in on Moodle
               // so we must now log-off the user from Moodle...
               require_logout();
        }
        return;
      }

      // The Drupal session is valid; now check if Moodle is logged in...
      if (isloggedin() && !isguestuser()) { return; }

      // Moodle is not logged in so fetch or create the corresponding user
      $user = $this->create_update_user($drupal_user);
      if (empty($user)) {
         // Something went wrong while creating the user
         print_error('auth_drupalservicescreateaccount', 'auth_drupalservices',
         $drupal_user->username);
         unset($drupal_user);
         return;
      }
      unset($drupal_user);

      // complete login
      $USER = get_complete_user_data('id', $user->id);
      complete_user_login($USER);

      // redirect
      if (isset($SESSION->wantsurl) and
         (strpos($SESSION->wantsurl, $CFG->wwwroot) == 0)) {
            // the URL is set and within Moodle's environment
            $urltogo = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
      } else {
            // no wantsurl stored or external link. Go to homepage.
            $urltogo = $CFG->wwwroot.'/';
            unset($SESSION->wantsurl);
      }
      redirect($urltogo);
   }

   // function to grab Moodle user and update their fields then return the
   // account. If the account does not exist, create it.
   // Returns: the Moodle user (array) associated with drupal user argument
   function create_update_user($drupal_user) {
      global $CFG, $DB;

     $uid = $drupal_user->uid;
     $username = $drupal_user->name;
     $email = $drupal_user->mail;
     //status should be 1.
     $status = $drupal_user->status;
     $timezone = $drupal_user->timezone;
     $firstname = $drupal_user->field_address->und[0]->first_name;
     $lastname = $drupal_user->field_address->und[0]->last_name;
     $city = $drupal_user->field_address->und[0]->locality;
     $country = $drupal_user->field_address->und[0]->country;

     // MIGHT DO THIS? $user = create_user_record($username, "", "joomdle");
     // and coodle do much better checks for updated fields. - $DB->update_record('user', $updateuser);
    
      //Look for user with idnumber = uid instead of using usernames as drupal username might have changed. 

      $user = $DB->get_record('user',
         array('idnumber'=>$uid, 'mnethostid'=>$CFG->mnet_localhost_id));

      if (empty($user)) {
            // build the new user object to be put into the Moodle database
            $user = new object();
            $user->username   = $username;
            $user->firstname  = $firstname;
            $user->lastname   = $lastname;
            $user->auth       = $this->authtype;
            $user->mnethostid = $CFG->mnet_localhost_id;
            //$user->lang       = str_replace('-', '_', $drupal_user->language);
            $user->lang       = $CFG->lang;
            $user->confirmed  = 1;
            $user->email      = $email;
            $user->idnumber   = $uid;
            $user->city       = $city;
            $user->country    = $country;
            $user->modified   = time();

            // add the new Drupal user to Moodle
            $uid = $DB->insert_record('user',$user);
            $user = $DB->get_record('user',
               array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id));
            if (!$user) {
               print_error('auth_drupalservicescantinsert','auth_db',$username);
            }
      } else {
            // Update user information
            //username "could" change in drupal. idnumber should never change.
            $user->username   = $username;
            $user->firstname  = $firstname;
            $user->lastname   = $lastname;
            $user->email      = $email;
            $user->city       = $city;
            $user->country    = $country;
            $user->auth       = $this->authtype;
            if (!$DB->update_record('user', $user)) {
                        print_error('auth_drupalservicescantupdate','auth_db',$username);
            }
      }
      return $user;
   }

   function logoutpage_hook() {
      global $CFG, $SESSION;

      $base_url = $this->config->hostname;
      $endpoint = $this->config->endpoint;

     if (isset($SESSION->drupal_session_name) && isset($SESSION->drupal_session_id)) {
      // logout of drupal.

      $session_name = $SESSION->drupal_session_name;
      $session_id = $SESSION->drupal_session_id;


       $apiObj = new RemoteAPI( $base_url, $endpoint, 1, $session_name, $session_id );

       $ret = $apiObj->Logout();

       if (is_null($ret)) {
         return;
       } else {
         return true;
       }
     }
     return; 
   }

   // cron synchronization script
   // $do_updates: true to update existing accounts (and add new Drupal accounts)
   function sync_users($do_updates=false) {
      global $CFG, $DB;
      // process users in Moodle that no longer exist in Drupal

      $remote_user = $this->config->remote_user;
      $remote_pw = $this->config->remote_pw;

      $base_url = $this->config->hostname;
      $endpoint = $this->config->endpoint;

      $apiObj = new RemoteAPI( $base_url, $endpoint );
 
      // here's the logic to login. Required for authentication, and all other operations:
      $ret = $apiObj->Login( $remote_user, $remote_pw); // pass in username and password

      if (is_null($ret)) {
        die("ERROR: Login error!\n");
      }

      print_r($apiObj);

      // list external users
      $ret = $apiObj->Index('user', '?pagesize=5000&fields=uid&parameters[status]=1');

      if (is_null($ret)) {
        die("ERROR: Problems trying to get index of users!\n");
      }

      $userlist = array();
      foreach( $ret as $user )
        {
           array_push($userlist, $user->uid);
        }

        if (!empty($this->config->removeuser)) {
            // find obsolete users
            if (count($userlist)) {
                list($notin_sql, $params) = $DB->get_in_or_equal($userlist, SQL_PARAMS_NAMED, 'u', false);
                $params['authtype'] = $this->authtype;
                $sql = "SELECT u.*
                          FROM {user} u
                         WHERE u.auth=:authtype AND u.deleted=0 AND u.idnumber $notin_sql";
            } else {
                $sql = "SELECT u.*
                          FROM {user} u
                         WHERE u.auth=:authtype AND u.deleted=0";
                $params = array();
                $params['authtype'] = $this->authtype;
            }
            $remove_users = $DB->get_records_sql($sql, $params);
            if (!empty($remove_users)) {
                //if ($verbose) {
                    mtrace(print_string('auth_drupalservicesuserstoremove','auth_drupalservices', count($remove_users)));
                //}

                foreach ($remove_users as $user) {
                    if ($this->config->removeuser == AUTH_REMOVEUSER_FULLDELETE) {
                        delete_user($user);
                        //if ($verbose) {
                            mtrace("\t".get_string('auth_drupalservicesdeleteuser', 'auth_drupalservices', array('name'=>$user->username, 'id'=>$user->id)));
                        //}
                    } else if ($this->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
                        $updateuser = new stdClass();
                        $updateuser->id   = $user->id;
                        $updateuser->auth = 'nologin';
                        $updateuser->timemodified = time();
                        $DB->update_record('user', $updateuser);
                 //       if ($verbose) {
                            mtrace("\t".get_string('auth_drupalservicessuspenduser', 'auth_drupalservices', array('name'=>$user->username, 'id'=>$user->id)));
                  //      }
                    }
                }
                unset($remove_users); // free mem!
            }
         }
      if (!count($userlist)) {
            // exit right here
            // nothing else to do
            return 0;
       }

      // sync users in Drupal with users in Moodle (adding users if needed)

      if ($do_updates) {
            // sync users in Drupal with users in Moodle (adding users if needed)
            // Not very efficient but hey?
            print_string('auth_drupalservicesuserstoupdate','auth_drupalservices',
               count($userlist));
            print "\n";

            foreach ($userlist as $uid) {

               if ($uid < 1) { //No anon 
                 print "Skipping anon user - uid $uid\n";
                 next;
               }

               //Get user details
               $drupal_user = $apiObj->Get('user', $uid);  // <- Get user 

               if (is_null($drupal_user)) {
                 print "ERROR: Error retreiving user $uid\n";
                 next;
               }

               print_string('auth_drupalservicesupdateuser', 'auth_drupalservices', $drupal_user->name . "\n");
               $user = $this->create_update_user($drupal_user);
               if (empty($user)) {
                   // Something went wrong while creating the user
                   print_error('auth_drupalservicescreateaccount', 'auth_drupalservices',
                   $drupal_user->name);
               } 
            }
            unset($userlist); // free mem! 
       }
       // END OF DO UPDATES

       //LOGOUT
       $ret = $apiObj->Logout();

       if (is_null($ret)) {
         print "ERROR logging out!\n";
       } else {
         print "Logged out from drupal services\n";
      }
   }

   // Function called by admin/auth.php to print a form for configuring plugin
   // @param array $page An object containing all the data for this page.
   function config_form($config, $err, $user_fields) {
      include 'config.html';
   }

   // Processes and stores configuration data for this authentication plugin.
   function process_config($config) {
      // set to defaults if undefined
      if (!isset($config->hostname)) {
            $config->hostname = 'http://';
      } else {
            // remove trailing slash
            $config->hostname = rtrim($config->hostname, '/');
      }
      if (!isset($config->endpoint)) {
            $config->endpoint = '';
      } else {
            if ((substr($config->endpoint, 0, 1) != '/')) {
              //no preceding slash! Add one!
              $config->endpoint = '/' . $config->endpoint;
            } 
            // remove trailing slash
            $config->endpoint = rtrim($config->endpoint, '/');
      }
      if (!isset($config->remote_user)) {
            $config->remote_user = '';
      }
      if (!isset($config->remote_pw)) {
            $config->remote_pw = '';
      }
      if (!isset($config->removeuser)) {
            $config->removeuser = AUTH_REMOVEUSER_KEEP;
      }
      if (!isset($config->cohorts)) {
          $config->cohorts = 0;
      }
      if (!isset($config->cohort_view)) {
          $config->cohort_view = '';
      }


      // Lock the idnumber as this is the drupal uid number
      // NOT WORKING!
      $config->field_lock_idnumber = 'locked';

      // save settings
      set_config('hostname',        $config->hostname,        'auth/drupalservices');
      set_config('endpoint',        $config->endpoint,        'auth/drupalservices');
      set_config('remote_user',     $config->remote_user,     'auth/drupalservices');
      set_config('remote_pw',       $config->remote_pw,       'auth/drupalservices');
      set_config('cohorts',         $config->cohorts,         'auth/drupalservices');
      set_config('cohort_view',     $config->cohort_view,     'auth/drupalservices');
      set_config('removeuser',      $config->removeuser,      'auth/drupalservices');
      set_config('field_lock_idnumber',      $config->field_lock_idnumber,      'auth/drupalservices');
      return true;
   }

  /**
  * Check to see if a user has been assigned a certain role.
  *
  *    @param $role
  *      The name of the role you're trying to find.
  * @param $user
  *      The user object for the user you're checking; defaults to the current user.
  * @return
  *   TRUE if the user object has the role, FALSE if it does not.
  */

  function user_has_role($role, $user = NULL) {

    if (is_array($user->roles) && in_array($role, array_values($user->roles))) {
      return TRUE;
    }

    return FALSE;
  }
  
  function get_drupal_session($base_url) {

      // Otherwise use $base_url as session name, without the protocol
      // to use the same session identifiers across http and https.
      list($protocol, $session_name) = explode('://', $base_url, 2);

      if (strtolower($protocol) == 'https') {
          $prefix = 'SSESS';
      } else {
          $prefix = 'SESS';
      }

      $session_name = $prefix . substr(hash('sha256', $session_name), 0, 32);

      if (isset($_COOKIE[$session_name])) {
        $session_id = $_COOKIE[$session_name];
        $return = array(
          'session_name' => $session_name,
          'session_id' => $session_id,
        );
        return $return;
      } else {
        return NULL;
      }
  }
}
