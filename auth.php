<?php
/**
 * Authentication Plugin: Drupal Services Single Sign-on
 *
 * This module is based on work by Arsham Skrenes.
 * This module will look for a Drupal cookie that represents a valid,
 * authenticated session, and will use it to create an authenticated Moodle
 * session for the same user. The Drupal user will be synchronized with the
 * corresponding user in Moodle. If the user does not yet exist in Moodle, it
 * will be created.
 *
 * PHP version 5
 *
 * @category CategoryName
 * @package  Drupal_Services 
 * @author   Dave Cannon <dave@baljarra.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link     https://github.com/cannod/moodle-drupalservices 
 *
 */
// This must be accessed from a Moodle page only!
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}
require_once $CFG->libdir . '/authlib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
require_once $CFG->dirroot . '/auth/drupalservices/REST-API.php';

/**
 * class auth_plugin_drupalservices 
 *
 * @category CategoryName
 * @package  Drupal_Services 
 * @author   Dave Cannon <dave@baljarra.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link     https://github.com/cannod/moodle-drupalservices
 */
class auth_plugin_drupalservices extends auth_plugin_base
{
    /**
     * Constructor
     */
    function auth_plugin_drupalservices()
    {
        $this->authtype = 'drupalservices';
        $this->config = get_config('auth/drupalservices');
    }
    /** 
     * This plugin is for SSO only; Drupal handles the login
     *
     * @param string $username the username 
     * @param string $password the password 
     *
     * @return int return FALSE
     */
    function user_login($username, $password)
    {
        return false;
    }
    /**
     * Function to enable SSO (it runs before user_login() is called)
     * If a valid Drupal session is not found, the user will be forced to the
     * login page where some other plugin will have to authenticate the user
     *
     * @return int return FALSE
     */
    function loginpage_hook()
    {
        global $CFG, $USER, $SESSION, $DB;
        // Check if we have a Drupal session.
        $base_url = $this->config->hostname;
        $endpoint = $this->config->endpoint;
        $drupalsession = $this->get_drupal_session($base_url);
        if (empty($drupalsession)) {
            // redirect to drupal login page with destination
            if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) == 0)) {
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
        // So we connect to system/connect and we should get a valid drupal user.
        $session_name = $drupalsession['session_name'];
        $session_id = $drupalsession['session_id'];
        $SESSION->drupal_session_name = $session_name;
        $SESSION->drupal_session_id = $session_id;
        $apiObj = new RemoteAPI($base_url, $endpoint, 1, $session_name, $session_id);
        // Connect to Drupal with this session
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
        // The Drupal session is valid; now check if Moodle is logged in...
        if (isloggedin() && !isguestuser()) {
            return;
        }
        // See if we have a moodle user with this idnumber 
        $user = get_complete_user_data('idnumber', $uid);

        if (empty($user)) {
          $newuser = $apiObj->Index('muser?uid='.$uid);
          $this->create_update_user($newuser[0]);
          $user = get_complete_user_data('idnumber', $uid);
        }
        // Complete the login
        complete_user_login($user);
        // redirect
        if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) == 0)) {
            // the URL is set and within Moodle's environment
            $urltogo = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
        } else {
            // no wantsurl stored or external link. Go to homepage.
            $urltogo = $CFG->wwwroot . '/';
            unset($SESSION->wantsurl);
        }
        redirect($urltogo);
    }
    /**
     * function to grab Moodle user and update their fields then return the
     * account. If the account does not exist, create it.
     * Returns: the Moodle user (array) associated with drupal user argument
     *
     * @param array $drupal_user the Drupal user array.
     *
     * @return array Moodle user 
     */
    function create_update_user($drupal_user)
    {
        global $CFG, $DB;
        $uid = $drupal_user->uid;
        $username = $drupal_user->name;
        $email = $drupal_user->email;
        //status should be 1.
        $status = $drupal_user->status;
        //$timezone = $drupal_user->timezone;
        $firstname = $drupal_user->firstname;
        $lastname = $drupal_user->lastname;
        $city = $drupal_user->city;
        $country = $drupal_user->country;
        // MIGHT DO THIS? $user = create_user_record($username, "", "joomdle");
        // and do better checks for updated fields. 
        // Maybe $DB->update_record('user', $updateuser);
        // Look for user with idnumber = uid instead of using usernames as
        // drupal username might have changed.
        $user = $DB->get_record('user', array('idnumber' => $uid, 'mnethostid' => $CFG->mnet_localhost_id));
        if (empty($user)) {
            // build the new user object to be put into the Moodle database
            $user = new object();
            $user->username = $username;
            $user->firstname = $firstname;
            $user->lastname = $lastname;
            $user->auth = $this->authtype;
            $user->mnethostid = $CFG->mnet_localhost_id;
            //$user->lang       = str_replace('-', '_', $drupal_user->language);
            $user->lang = $CFG->lang;
            $user->confirmed = 1;
            $user->email = $email;
            $user->idnumber = $uid;
            $user->city = $city;
            $user->country = $country;
            $user->modified = time();
            // add the new Drupal user to Moodle
            $uid = $DB->insert_record('user', $user);
            $user = $DB->get_record('user', array('username' => $username, 'mnethostid' => $CFG->mnet_localhost_id));
            if (!$user) {
                print_error('auth_drupalservicescantinsert', 'auth_db', $username);
            }
        } else {
            // Update user information
            //username "could" change in drupal. idnumber should never change.
            $user->username = $username;
            $user->firstname = $firstname;
            $user->lastname = $lastname;
            $user->email = $email;
            $user->city = $city;
            $user->country = $country;
            $user->auth = $this->authtype;
            if (!$DB->update_record('user', $user)) {
                print_error('auth_drupalservicescantupdate', 'auth_db', $username);
            }
        }
        return $user;
    }
    /**
     * Run before logout
     *
     * @return int TRUE if valid session. 
     */
    function logoutpage_hook()
    {
        global $CFG, $SESSION;
        $base_url = $this->config->hostname;
        $endpoint = $this->config->endpoint;
        if (isset($SESSION->drupal_session_name) && isset($SESSION->drupal_session_id)) {
            // logout of drupal.
            $session_name = $SESSION->drupal_session_name;
            $session_id = $SESSION->drupal_session_id;
            $apiObj = new RemoteAPI($base_url, $endpoint, 1, $session_name, $session_id);
            $ret = $apiObj->Logout();
            if (is_null($ret)) {
                return;
            } else {
                return true;
            }
        }
        return;
    }
    /**
     * cron synchronization script
     *
     * @param int $do_updates true to update existing accounts
     *
     * @return int       
     */
    function sync_users($do_updates = false)
    {
        global $CFG, $DB;
        // process users in Moodle that no longer exist in Drupal
        $remote_user = $this->config->remote_user;
        $remote_pw = $this->config->remote_pw;
        $base_url = $this->config->hostname;
        $endpoint = $this->config->endpoint;
        $apiObj = new RemoteAPI($base_url, $endpoint);
        // Required for authentication, and all other operations:
        $ret = $apiObj->Login($remote_user, $remote_pw, true);
        if ($ret->info['http_code']==404) {
          die("ERROR: Login service unreachable!\n");
        }
        if ($ret->info['http_code']==401) {
          die("ERROR: Login failed - check username and password!\n");
        }
        // list external users
        $drupal_users = $apiObj->Index('muser');
        if (is_null($drupal_users) || empty($drupal_users)) {
            die("ERROR: Problems trying to get index of users!\n");
        }
        $userlist = array();
        foreach ($drupal_users as $drupal_user) {
            array_push($userlist, $drupal_user->uid);
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
                mtrace(print_string('auth_drupalservicesuserstoremove', 'auth_drupalservices', count($remove_users)));
                //}
                foreach ($remove_users as $user) {
                    if ($this->config->removeuser == AUTH_REMOVEUSER_FULLDELETE) {
                        delete_user($user);
                        //if ($verbose) {
                        mtrace("\t" . get_string('auth_drupalservicesdeleteuser', 'auth_drupalservices', array('name' => $user->username, 'id' => $user->id)));
                        //}
                        
                    } else if ($this->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
                        $updateuser = new stdClass();
                        $updateuser->id = $user->id;
                        $updateuser->auth = 'nologin';
                        $updateuser->timemodified = time();
                        $DB->update_record('user', $updateuser);
                        //       if ($verbose) {
                        mtrace("\t" . get_string('auth_drupalservicessuspenduser', 'auth_drupalservices', array('name' => $user->username, 'id' => $user->id)));
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
            print_string('auth_drupalservicesuserstoupdate', 'auth_drupalservices', count($userlist));
            print "\n";
            foreach ($drupal_users as $drupal_user) {
                if ($drupal_user->uid < 1) { //No anon
                    print "Skipping anon user - uid $uid\n";
                    continue;
                }
                print_string('auth_drupalservicesupdateuser', 'auth_drupalservices', $drupal_user->name . '(' . $drupal_user->uid . ')' . "\n");
                $user = $this->create_update_user($drupal_user);
                if (empty($user)) {
                    // Something went wrong while creating the user
                    print_error('auth_drupalservicescreateaccount', 'auth_drupalservices', $drupal_user->name);
                    continue; //Next user
                }
            }
        } // END OF DO UPDATES
        // Now do cohorts
        if (($do_updates) && ($this->config->cohorts != 0)) {
            $cohort_view = $this->config->cohort_view;
            print "Updating cohorts using services view - $cohort_view\n";
            $context = get_context_instance(CONTEXT_SYSTEM);
            //$processed_cohorts_list = array();
            $drupal_cohorts = $apiObj->Index($cohort_view);
            if (is_null($drupal_cohorts)) {
                print "ERROR: Error retreiving cohorts!\n";
            } else {
                // OK First lets create any Moodle cohorts that are in drupal.
                foreach ($drupal_cohorts as $drupal_cohort) {
                    if ($drupal_cohort->cohort_name == '') {
                        continue; // We don't want an empty cohort name
                        
                    }
                    $drupal_cohort_list[] = $drupal_cohort->cohort_name;
                    if (!$this->cohort_exists($drupal_cohort->cohort_name)) {
                        $newcohort = new stdClass();
                        $newcohort->name = $drupal_cohort->cohort_name;
                        $newcohort->idnumber = $drupal_cohort->cohort_id;
                        $newcohort->description = $drupal_cohort->cohort_description;
                        $newcohort->contextid = $context->id;
                        $newcohort->component = 'auth_drupalservices';
                        $cid = cohort_add_cohort($newcohort);
                        print "Cohort $drupal_cohort->cohort_name ($cid) created!\n";
                    }
                }
                // Next lets delete any Moodle cohorts that are not in drupal.
                // Now create a unique array
                $drupal_cohort_list = array_unique($drupal_cohort_list);
                //print_r($drupal_cohort_list);
                $moodle_cohorts = $this->moodle_cohorts();
                //print_r($moodle_cohorts);
                foreach ($moodle_cohorts as $moodle_cohort) {
                    if (array_search($moodle_cohort->name, $drupal_cohort_list) === false) {
                        print "$moodle_cohort->name not in drupal - deleteing\n";
                        cohort_delete_cohort($moodle_cohort);
                    }
                    $moodle_cohorts_list[$moodle_cohort->id] = $moodle_cohort->name;
                }
                // Cool. Now lets go through each user and add them to cohorts.
                // arrays to use? $userlist - list of uids.
                // $drupal_cohorts - view. $drupal_cohorts_list. Moodle lists.
                foreach ($userlist as $uid) {
                    $drupal_user_cohort_list = array();
                    //print "$uid\n";
                    $user = $DB->get_record('user', array('idnumber' => $uid, 'mnethostid' => $CFG->mnet_localhost_id));
                    // Get array of cohort names this user belongs to.
                    $drupal_user_cohorts = $this->drupal_user_cohorts($uid, $drupal_cohorts);
                    foreach ($drupal_user_cohorts as $drupal_user_cohort) {
                        //get the cohort id frm the moodle list.
                        $cid = array_search($drupal_user_cohort->cohort_name, $moodle_cohorts_list);
                        //print "$cid\n";
                        if (!$DB->record_exists('cohort_members', array('cohortid' => $cid, 'userid' => $user->id))) {
                            cohort_add_member($cid, $user->id);
                            print "Added $user->username ($user->id) to cohort $drupal_user_cohort->cohort_name\n";
                        }
                        // Create a list of enrolled cohorts to use later.
                        $drupal_user_cohort_list[] = $cid;
                    }
                    // Cool. now get this users list of moodle cohorts and compare
                    // with drupal. remove from moodle if needed.
                    $moodle_user_cohorts = $this->moodle_user_cohorts($user);
                    //print_r($moodle_user_cohorts);
                    foreach ($moodle_user_cohorts as $moodle_user_cohort) {
                        if (array_search($moodle_user_cohort->cid, $drupal_user_cohort_list) === false) {
                            cohort_remove_member($moodle_user_cohort->cid, $user->id);
                            print "Removed $user->username ($user->id) from cohort $moodle_user_cohort->name\n";
                        }
                    }
                }
            }
        } // End of cohorts
        //LOGOUT
        $ret = $apiObj->Logout();
        if (is_null($ret)) {
            print "ERROR logging out!\n";
        } else {
            print "Logged out from drupal services\n";
        }
    }
    /**
     * Function called by admin/auth.php to print a form for configuring plugin
     *
     * @param array $config      needed?
     * @param array $err         needed?
     * @param array $user_fields needed?
     *
     * @return int TRUE
     */
    function config_form($config, $err, $user_fields)
    {
        if($config->hostname){
          $base_url = $config->hostname;
          $drupalsession = $this->get_drupal_session($base_url);
          $remote_user = $config->remote_user;
          $remote_pw = $config->remote_pw;

          //test #1: cookie found?
          if($drupalsession){
            $tests['cookie']=array('success'=>(bool)$drupalsession, 'message'=>"cookies: SSO Cookie discovered properly");
          }
          else{
            $tests['cookie']=array('success'=>(bool)$drupalsession, 'message'=>"cookies: SSO Cookie not discovered. 1) check that you are currently logged in to drupal. 2) Check that Drupal's session cookie is configured in settings.php 3) check that cookie_domain is properly filled in.");
          }

          //test #2: service endpoints reachable?
          $endpoint = $config->endpoint;

          $apiObj = new RemoteAPI($base_url, $endpoint, 1, $drupalsession['session_name'], $drupalsession['session_id']);
          // Connect to Drupal with this session
          $ret = $apiObj->Connect(true);

          if($ret){
            if($ret->response->user->uid){
              $tests['session']=array('success'=>true, 'message'=>"system/connect: User session data reachable and you are logged in!");
            }
            elseif($ret->info['http_code']==406){ // code for unsupported http request
              $tests['session']=array('success'=>false, 'message'=>"system/connect: The drupal services endpoint is not accepting JSON requests. Please confirm that at least the JSON response formatter is checked, and at least the \"application/x-www-form-urlencoded\" request parsing header. (application/json is also recommended)");
            }
            else{
              $tests['session']=array('success'=>false, 'message'=>"system/connect: User session data reachable but you aren't logged in!");
            }

          }
          else{
            $tests['session']=array('success'=>false, 'message'=> "system/connect: User session data unreachable. Ensure that the server is reachable, and that the 'session/connect' service is enable for this endpoint");
          }

          //test #3: authentication
          $apiObj = new RemoteAPI($base_url, $endpoint);
          // Required for authentication, and all other operations:
          $ret = $apiObj->Login($remote_user, $remote_pw, true);

          if($ret->info['http_code']==406){
            $tests['auth']=array('success'=>false, 'message'=> "user/login: The drupal services endpoint is not accepting JSON requests. Please confirm that at least the JSON response formatter is checked, and at least the \"application/x-www-form-urlencoded\" request parsing header. (application/json is also recommended)");
          }
          if($ret->info['http_code']==404){
            $tests['auth']=array('success'=>false, 'message'=> "user/login: Login service unreachable. Check that User/actions/login is enabled in the Drupal moodle services endpoint.");
          }
          elseif($ret->info['http_code']==401){
            $tests['auth']=array('success'=>false, 'message'=> "user/login: Login to drupal failed. Check that the username and password are correct.");
          }
          elseif($ret->info['http_code']==200){
            $tests['auth']=array('success'=>true, 'message'=> "user/login: Logged in to drupal!");
          }

          //test #4: user listings
          $drupal_users = $apiObj->Index('muser', null, true); //get a full listing, in debug mode

          if($drupal_users==null){
            $tests['userlisting']=array('success'=>false, 'message'=> "muser/Index: An authentication error occurred");
          }
          elseif($drupal_users->info['http_code']==404){
            $tests['userlisting']=array('success'=>false, 'message'=> "muser/Index: The muser resource is not available in the drupal service endpoint.");
          }
          elseif($drupal_users->info['http_code']==403){
            $tests['userlisting']=array('success'=>false, 'message'=> "muser/Index: The user account specified does not have access to the muser service. Check that the access permissions are correct in the muser view's service display");
          }
          elseif($drupal_users->info['http_code']==200 && !count($drupal_users->userlist)){
            $tests['userlisting']=array('success'=>false, 'message'=> "muser/Index: No users were returned. Are the filters set up properly in the view?");
          }
          elseif($drupal_users->info['http_code']==200 && count($drupal_users->userlist)){
            $tests['userlisting']=array('success'=>true, 'message'=> "muser/Index: User listings are active!");
          }
        }
        else{
          $tests['configuration']=array('success'=>false, 'message'=> "no configuration data yet!");
        }
        include 'config.html';
    }
    /** 
     * Processes and stores configuration data for this authentication plugin.
     *
     * @param array $config main config 
     *
     * @return int TRUE
     */
    function process_config($config)
    {
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
        set_config('hostname', $config->hostname, 'auth/drupalservices');
        set_config('cookiedomain', $config->cookiedomain, 'auth/drupalservices');
        set_config('endpoint', $config->endpoint, 'auth/drupalservices');
        set_config('remote_user', $config->remote_user, 'auth/drupalservices');
        set_config('remote_pw', $config->remote_pw, 'auth/drupalservices');
        set_config('cohorts', $config->cohorts, 'auth/drupalservices');
        set_config('cohort_view', $config->cohort_view, 'auth/drupalservices');
        set_config('removeuser', $config->removeuser, 'auth/drupalservices');
        set_config('field_lock_idnumber', $config->field_lock_idnumber, 'auth/drupalservices');
        return true;
    }
    /**
     * Check if cohort exists. return true if so.
     *
     * @param string $drupal_cohort_name name of drupal cohort 
     *
     * @return int TRUE
     */
    function cohort_exists($drupal_cohort_name)
    {
        global $DB;
        $context = get_context_instance(CONTEXT_SYSTEM);
        $clause = array('contextid' => $context->id);
        $clause['component'] = 'auth_drupalservices';
        $moodle_cohorts = $DB->get_records('cohort', $clause);
        foreach ($moodle_cohorts as $moodle_cohort) {
            if ($drupal_cohort_name == $moodle_cohort->name) {
                return true;
            }
        }
        // no match so return false
        return false;
    }
    /**
     * return list of cohorts
     *
     * @return array moodle cohorts 
     */
    function moodle_cohorts()
    {
        global $DB;
        $context = get_context_instance(CONTEXT_SYSTEM);
        $clause = array('contextid' => $context->id);
        $clause['component'] = 'auth_drupalservices';
        $moodle_cohorts = $DB->get_records('cohort', $clause);
        //foreach ($moodle_cohorts as $moodle_cohort) {
        //  $moodle_cohorts_list[$moodle_cohort->id] = $moodle_cohort->name;
        // }
        return $moodle_cohorts;
    }
    /**
     * Return an array of cohorts this uid is in.
     *
     * @param int   $uid            The drupal UID
     * @param array $drupal_cohorts All drupal cohorts 
     *
     * @return array cohorts for this user 
     */
    function drupal_user_cohorts($uid, $drupal_cohorts)
    {
        $user_cohorts = array();
        foreach ($drupal_cohorts as $drupal_cohort) {
            if ($uid == $drupal_cohort->uid) {
                //$user_cohorts[] = $drupal_cohort->cohort_name;
                $user_cohorts[] = $drupal_cohort;
            }
        }
        return $user_cohorts;
    }
    /**
     * Return an array of moodle cohorts this user is in.
     *
     * @param array $user Moodle user 
     *
     * @return array cohorts for this user
     */
    function moodle_user_cohorts($user)
    {
        global $DB;
        $sql = "SELECT c.id AS cid, c.name AS name FROM {cohort} c JOIN {cohort_members} cm ON cm.cohortid = c.id WHERE c.component = 'auth_drupalservices' AND cm.userid = $user->id";
        $user_cohorts = $DB->get_records_sql($sql);
        return $user_cohorts;
    }
    /**
     * Get Drupal session 
     *
     * @param string $base_url This base URL
     *
     * @return array session_name and session_id 
     */ 
    function get_drupal_session($base_url)
    {
        $cfg=get_config('auth/drupalservices');
        // Otherwise use $base_url as session name, without the protocol
        // to use the same session identifiers across http and https.
        list($protocol, $session_name) = explode('://', $base_url, 2);
        if (strtolower($protocol) == 'https') {
            $prefix = 'SSESS';
        } else {
            $prefix = 'SESS';
        }
        $session_name=$cfg->cookiedomain;

        $session_name = $prefix . substr(hash('sha256', '.'.$session_name), 0, 32);
        if (isset($_COOKIE[$session_name])) {
            $session_id = $_COOKIE[$session_name];
            $return = array('session_name' => $session_name, 'session_id' => $session_id,);
            return $return;
        } else {
            return null;
        }
    }
}
