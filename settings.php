<?php
require_once $CFG->libdir . '/authlib.php';
require_once $CFG->dirroot . '/auth/drupalservices/auth.php';

//todo: this seemingly gets included 3 times when submitted - lets find out why
// my guess is that its to run a validate/submit/load command set


/**
 * The two most important settings are the endpoint_uri and the cookiedomain.
 * The cookiedomain should be fairly easy to derive (see below) but the endpoint might be harder.
 * there are many possible configurations that mire this issue. For multidomain configurations the
 * setup could be:
 * 1) moodle subdomain - drupal master domain
 * moodleurl: moodle.example.com
 * drupalurl: example.com
 * cookie: .example.com
 * 2) completely different subdomains:
 * moodleurl: moodle.examle.com
 * drupalurl: drupal.example.com
 * cookie: .example.com
 * 3) moodle master domain - drupal subdomain
 * moodleurl: example.com
 * drupalurl: moodle.example.com
 * cookie: .example.com
 *
 * Additionally there are a number of setups that involve using a moodle subdirectory of a drupal site
 * this can be in the form of:
 * 1) example.com/moodle
 * 2) drupal.example.com/moodle
 *
 * because we can't guess at a name for a subdomain that is different than our own, the only cases we can
 * capture at the moment are case #1 for subdomains and the two cases for directories.
 *
 * A possible option for capturing the drupal domain (cases #2 & #3) in the future is to leverage the
 * drupal_sso module to set a cookie on the admin user that states the domain the cookie was issued
 * this could then be used during first time configuration and automate the settings better.
**/

// define default settings:
$defaults=array(
  'endpoint_uri' => $CFG->wwwroot."/moodlesso",
  'sso_method' => AUTH_DRUPALSERVICES_SSO_AUTODETECT,
  'cookiedomain' => '',
  'remote_user' => '',
  'remote_pw' => '',
  'remove_user' => AUTH_REMOVEUSER_KEEP,
  'cohorts' => 0,
  'cohort_view' => "",
);

$config=get_config('auth/drupalservices');
//if the configuration has never been set, we want the autodetect script to activate
$autodetect=!empty($config->endpoint_uri);
$config=(array)$config + $defaults;

// the defaults give us enough to actually start the endpoint/sso configuration and tests

// detecting the sso cookie is the hard part because we need to check all of the valid subdomains against
// all of the subdirectories till a match is found. Here's an example and how it will be scanned:

// example full path: http://moodle.intranet.example.com/example/drupal/drupalsso
// moodle.intranet.example.com/example/drupal/drupalsso
// moodle.intranet.example.com/example/drupal
// moodle.intranet.example.com/example/
// .intranet.example.com/example/drupal/drupalsso
// .intranet.example.com/example/drupal
// .intranet.example.com/example
// .intranet.example.com
// .example.com/example/drupal/drupalsso
// .example.com/example/drupal
// .example.com/example
// .example.com

// if/when a match is found the proper settings will be saved and used. if not, a message will be displayed

$cookiebydomain=$config['endpoint_uri'];

// use a do/while because each of the loops need to run at least one time.
do {
  $cookiebypath=$cookiebydomain;
  do{
    // generate a mock config where the base url and cookiedomain get modified
    $test=parse_url($cookiebypath);
    // Check to see if the cookie domain is set to use a wildcard for this domain
    // it is more likely that this will happen than the other one, so this check is first
    $testconfig->cookiedomain = "." . $test['host'] . $test['path'];
    $sso_config_discovered = auth_plugin_drupalservices::get_drupal_session($cookiebypath, $testconfig);
    if(!$sso_config_discovered) {
      // check to see if the cookie is set to be this direct path (in the case of moodle/drupal in subdirectory mode)
      $testconfig->cookiedomain=$test['host'].$test['path'];
      $sso_config_discovered=auth_plugin_drupalservices::get_drupal_session($cookiebypath, $testconfig);
    }
  // loop again until there are no items left in the path part of the url
  }while(!$sso_config_discovered && $cookiebypath=auth_plugin_drupalservices::dereference_url($cookiebypath, false));
//loop again until there is only one item left in the domain part of the url
}while(!$sso_config_discovered && $cookiebydomain=auth_plugin_drupalservices::dereference_url($cookiebydomain, true));

// if the right cookie domain setting was discovered, set it to the proper config variable

if($sso_config_discovered){
  $config['endpoint_uri']=$cookiebydomain;
  $config['cookiedomain']=$testconfig->cookiedomain;

  //now that the cookie domain is discovered, try to reach out to the endpoint to test SSO
  $apiObj = new RemoteAPI($config['endpoint_uri'], 1, $sso_config_discovered['session_name'], $sso_config_discovered['session_id']);
  // Connect to Drupal with this session
  $ret = $apiObj->Connect(true);
  if($ret){
    if($ret->response->user->uid){
      $tests['session']=array('success'=>true, 'message'=>"system/connect: User session data reachable and you are logged in!");
    }
    else{
      $tests['session']=array('success'=>false, 'message'=>"system/connect: User session data reachable but you aren't logged in!");
    }
    // we found valid values for the endpoint and cookie domain - autoset them here
    if($autodetect) {
      set_config('endpoint_uri', $config['endpoint_uri'], 'auth/drupalservices');
      set_config('cookiedomain', $config['cookiedomain'], 'auth/drupalservices');
    }
  }
  else{
    $tests['session']=array('success'=>false, 'message'=> "system/connect: User session data unreachable. Ensure that the server is reachable");
  }

}



$defaults=(object)$defaults;
$config=(object)$config;

$drupalssosettings = new admin_settingpage('authsettingdrupalservices', new lang_string('pluginname', 'auth_drupalservices'),
  array('auth/drupalservices:config'));

//todo: these should be in a fieldset related to sso config. a heading will do for now
$drupalssosettings->add(new admin_setting_heading('drupalsso_settings', new lang_string('servicesettings_header', 'auth_drupalservices'), new lang_string('servicesettings_header_info', 'auth_drupalservices')));

$drupalssosettings->add(new admin_setting_configtext('auth/drupalservices/endpoint_uri',
  new lang_string('auth_drupalservices_endpoint_uri_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservices_endpoint_uri', 'auth_drupalservices'),
  $defaults->endpoint_uri, PARAM_TEXT));

////todo: autodetect the drupal session cookie and suggest an option here
//$drupalssosettings->add(new admin_setting_configselect('auth/drupalservices/sso_method',
//  new lang_string('auth_drupalservices_sso_method_key', 'auth_drupalservices'),
//  new lang_string('auth_drupalservices_sso_method', 'auth_drupalservices'),
//  $defaults->sso_method, array(
//    AUTH_DRUPALSERVICES_SSO_AUTODETECT => get_string('AUTH_DRUPALSERVICES_SSO_AUTODETECT','auth_drupalservices'),
//    AUTH_DRUPALSERVICES_SSO_CUSTOM => get_string('AUTH_DRUPALSERVICES_SS0_CUSTOM','auth_drupalservices'),
//  )));

$drupalssosettings->add(new admin_setting_configtext('auth/drupalservices/cookiedomain',
  new lang_string('auth_drupalservicescookiedomain_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservicescookiedomain', 'auth_drupalservices'),
  $defaults->cookiedomain, PARAM_TEXT));

$drupalssosettings->add(new admin_setting_configcheckbox('forcelogin',
  new lang_string('forcelogin', 'admin'),
  new lang_string('configforcelogin', 'admin'), 0));

//todo: these should be in a fieldset. a heading will do for now
$drupalssosettings->add(new admin_setting_heading('drupalsso_userfieldmap', new lang_string('userfieldmap_header', 'auth_drupalservices'), new lang_string('userfieldmap_header_desc', 'auth_drupalservices')));

//todo: these should be in a fieldset related to importing users. a heading will do for now
$drupalssosettings->add(new admin_setting_heading('drupalsso_userimport', new lang_string('userimport_header', 'auth_drupalservices'), new lang_string('userimport_header_desc', 'auth_drupalservices')));

$drupalssosettings->add(new admin_setting_configtext('auth/drupalservices/remote_user',
  new lang_string('auth_drupalservices_remote_user_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservices_remote_user', 'auth_drupalservices'),
  $defaults->remote_user, PARAM_TEXT));
$drupalssosettings->add(new admin_setting_configpasswordunmask('auth/drupalservices/remote_pw',
  new lang_string('auth_drupalservices_remote_pw_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservices_remote_pw', 'auth_drupalservices'),
  $defaults->remote_pw, PARAM_TEXT));

$drupalssosettings->add(new admin_setting_configselect('auth/drupalservices/remove_user',
  new lang_string('auth_drupalservicesremove_user_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservicesremove_user', 'auth_drupalservices'),
  $defaults->remove_user, array(
    AUTH_REMOVEUSER_KEEP => get_string('auth_remove_keep','auth'),
    AUTH_REMOVEUSER_SUSPEND => get_string('auth_remove_suspend','auth'),
    AUTH_REMOVEUSER_FULLDELETE => get_string('auth_remove_delete','auth'),
  )));

//todo: these fields shouldn't be here if cohorts are not enabled in moodle
$drupalssosettings->add(new admin_setting_configselect('auth/drupalservices/cohorts',
  new lang_string('auth_drupalservices_cohorts_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservices_cohorts', 'auth_drupalservices'),
  $defaults->cohorts, array( get_string('no'), get_string('yes'))));

$drupalssosettings->add(new admin_setting_configtext('auth/drupalservices/cohort_view',
  new lang_string('auth_drupalservices_cohort_view_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservices_cohort_view', 'auth_drupalservices'),
  $defaults->cohort_view, PARAM_TEXT));

$ADMIN->add('authsettings', $drupalssosettings);

