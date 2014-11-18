<?php

require_once $CFG->libdir . '/authlib.php';
require_once $CFG->dirroot . '/auth/drupalservices/auth.php';

//this really shouldn't have to be reinstantiated
$druaplauth= get_auth_plugin('drupalservices');

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
  'host_uri' => $CFG->wwwroot,
  'cookiedomain' => '',
  'remote_user' => '',
  'remote_pw' => '',
  'remove_user' => AUTH_REMOVEUSER_KEEP,
  'cohorts' => 0,
  'cohort_view' => "",
);

$config = get_config('auth_drupalservices');
//if the configuration has never been set, we want the autodetect script to activate
$configempty=empty($config->host_uri);

// merge in the defaults
$config=(array)$config + $defaults;

// the defaults give us enough to actually start the endpoint/sso configuration and tests

if($configempty){
  // autodetect sso settings
  if($base_sso_settings=auth_plugin_drupalservices::detect_sso_settings($config['host_uri'])){
    //merge in the resulting settings
    $config=$base_sso_settings + $config;
  }
}

// switch these over to objects now that all the merging is done
$defaults=(object)$defaults;
$config=(object)$config;


$endpoint_reachable=false;


$drupalserver=new RemoteAPI($config->host_uri);
// the settings service is public/public and just returns the cookiedomain and user field names (not data)
if($remote_settings = $drupalserver->Settings()){
  $endpoint_reachable=true;
  //we connected and the service is actively responding
  set_config('host_uri', $config->host_uri, 'auth_drupalservices');
  //if the cookie domain hasn't been previously set, set it now
  if(!$config->cookiedomain && $configempty){
    // the cookiedomain should get received via the Settings call
    $config->cookiedomain=$remote_settings['settings']['cookiedomain'];
    set_config('cookiedomain', $config->host_uri, 'auth_drupalservices');
  }
}

if($config->cookiedomain) {
  $drupalsession=auth_plugin_drupalservices::get_drupal_session($config);


  //now that the cookie domain is discovered, try to reach out to the endpoint to test SSO
  $apiObj = new RemoteAPI($config->host_uri, 1, $drupalsession);
  // Connect to Drupal with this session

  if ($loggedin_user = $apiObj->Connect()) {
    if ($ret->response->user->uid) {
      $endpoint_reachable=true;
      $tests['session'] = array('success' => true, 'message' => "system/connect: User session data reachable and you are logged in!");
    } else {
      $tests['session'] = array('success' => false, 'message' => "system/connect: User session data reachable but you aren't logged in!");
    }
  } else {
    $tests['session'] = array('success' => false, 'message' => "system/connect: User session data unreachable. Ensure that the server is reachable");
  }

  //this data should be cached - its possible that a non-admin user
  $fulluser=(array)$apiObj->Index("user/".$loggedin_user->user->uid);

  // turn the fulluser fields into key/value options
  $fulluser_keys=array_combine(array_keys($fulluser), array_keys($fulluser));
}


$drupalssosettings = new admin_settingpage('authsettingdrupalservices', new lang_string('pluginname', 'auth_drupalservices'),
  array('auth/drupalservices:config'));


// build an endpoint status item here:
$drupalssosettings->add(new admin_setting_heading('drupalsso_status', new lang_string('servicestatus_header', 'auth_drupalservices'), new lang_string('servicestatus_header_info', 'auth_drupalservices')));


//todo: these should be in a fieldset related to sso config. a heading will do for now
$drupalssosettings->add(new admin_setting_heading('drupalsso_settings', new lang_string('servicesettings_header', 'auth_drupalservices'), new lang_string('servicesettings_header_info', 'auth_drupalservices')));

$drupalssosettings->add(new admin_setting_configtext('auth_drupalservices/host_uri',
  new lang_string('auth_drupalservices_host_uri_key', 'auth_drupalservices'),
  new lang_string('auth_drupalservices_host_uri', 'auth_drupalservices'),
  $defaults->host_uri, PARAM_TEXT));

// don't allow configurations unless the endpoint is reachable
if($config->cookiedomain && $endpoint_reachable) {
  $drupalssosettings->add(new admin_setting_configcheckbox('forcelogin',
    new lang_string('forcelogin', 'admin'),
    new lang_string('configforcelogin', 'admin'), 0));

  //todo: these should be in a fieldset. a heading will do for now
  $drupalssosettings->add(new admin_setting_heading('drupalsso_userfieldmap', new lang_string('userfieldmap_header', 'auth_drupalservices'), new lang_string('userfieldmap_header_desc', 'auth_drupalservices')));

  foreach($druaplauth->userfields as $field){
    $drupalssosettings->add(new admin_setting_configselect('auth_drupalservices/field_map_'.$field,
      $field,
      new lang_string('fieldmap', 'auth_drupalservices',array('field'=>$field)),
      null,
      array(''=>"-- select --") + $fulluser_keys
      ));
  }

  //todo: these should be in a fieldset related to importing users. a heading will do for now
  $drupalssosettings->add(new admin_setting_heading('drupalsso_userimport', new lang_string('userimport_header', 'auth_drupalservices'), new lang_string('userimport_header_desc', 'auth_drupalservices')));

  $drupalssosettings->add(new admin_setting_configtext('auth_drupalservices/remote_user',
    new lang_string('auth_drupalservices_remote_user_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_remote_user', 'auth_drupalservices'),
    $defaults->remote_user, PARAM_TEXT));
  $drupalssosettings->add(new admin_setting_configpasswordunmask('auth_drupalservices/remote_pw',
    new lang_string('auth_drupalservices_remote_pw_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_remote_pw', 'auth_drupalservices'),
    $defaults->remote_pw, PARAM_TEXT));

//  $drupalssosettings->add(new admin_setting_configselect('auth_drupalservices/remove_user',
//    new lang_string('auth_drupalservicesremove_user_key', 'auth_drupalservices'),
//    new lang_string('auth_drupalservicesremove_user', 'auth_drupalservices'),
//    $defaults->remove_user, array(
//      AUTH_REMOVEUSER_KEEP => get_string('auth_remove_keep', 'auth'),
//      AUTH_REMOVEUSER_SUSPEND => get_string('auth_remove_suspend', 'auth'),
//      AUTH_REMOVEUSER_FULLDELETE => get_string('auth_remove_delete', 'auth'),
//    )));

  //todo: these fields shouldn't be here if cohorts are not enabled in moodle
  $drupalssosettings->add(new admin_setting_configselect('auth_drupalservices/cohorts',
    new lang_string('auth_drupalservices_cohorts_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_cohorts', 'auth_drupalservices'),
    $defaults->cohorts, array(get_string('no'), get_string('yes'))));

  $drupalssosettings->add(new admin_setting_configtext('auth_drupalservices/cohort_view',
    new lang_string('auth_drupalservices_cohort_view_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_cohort_view', 'auth_drupalservices'),
    $defaults->cohort_view, PARAM_TEXT));
}
$ADMIN->add('authsettings', $drupalssosettings);

