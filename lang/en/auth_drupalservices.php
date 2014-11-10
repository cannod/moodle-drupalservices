<?php
$string['pluginname'] = 'Drupal Services';
$string['servicesettings_header'] = 'Drupal Webservice Settings';
$string['servicesettings_header_info'] = '
  The following settings tell Moodle how to reach the Drupal Service endpoint that is required to set up SSO.
  If this is the first time this plugin is being configured, Moodle will attempt to automatically discover
  the correct SSO configuration, and notify you if successful. Please make sure the following items have taken place:<br>
  <ul>
    <li>You are currently logged in to your Drupal site as the Admin user</li>
    <li>You have enabled the moodle_sso module from http://drupal.org/project/moodle_sso</li>
    <li>You have set the cookie domain variable in the settings.php file of <b>Drupal</b> to work correctly</li>
  </ul>';

$string['userfieldmap_header'] = 'User field mappings';
$string['userfieldmap_header_desc'] = 'The user field mappings correlate Moodle user profile fields with Drupal user profile fields. Theses will be updated when users first log in to moodle, and each time the moodle session refreshes. Should bulk user importing be configured and enabled below, Moodle user profiles will also be updated on the next sync run after they are changed.';


$string['userimport_header'] = 'User import/migration settings';
$string['userimport_header_desc'] = 'These settings apply to bulk importing users from Drupal to Moodle via the sync_users.php script. A user account must be created in Drupal that has the "Moodle Services" role associated with it. That users credentials need to be supplied below. Each user imported will have profile values that use the field mappings set in the previous section.';

$string['auth_drupalservicesdescription'] = 'This authentication plugin enables Single Sign-on (SSO) with Drupal. This module will look for a Drupal cookie that represents a valid, authenticated session, and will use it to create an authenticated Moodle session for the same user. The Drupal user will be synchronized with the corresponding user in Moodle. If the user does not yet exist in Moodle, it will be created. Drupal services must be installed and configured on drupal. Please read the README file for installation instructions.';

$string['auth_drupalservices_sso_method_key'] = 'SSO Method';
$string['auth_drupalservices_sso_method'] = 'Choose the way your drupal and moodle sites support SSO. This can either be via subdomain (EG: moodle.example.com and drupal.example.com) or via subdirectories (EG: example.com/moodle). If subdomain is your choice, the first namepart will be removed. If you need more nameparts removed, choose the "custom" option, and supply your own cookie domain.';
$string['AUTH_DRUPALSERVICES_SSO_AUTODETECT']='Autodetect';
$string['AUTH_DRUPALSERVICES_SS0_CUSTOM']='Custom';

$string['auth_drupalservices_endpoint_uri_key'] = 'Drupal Endpoint URI';
$string['auth_drupalservices_endpoint_uri'] = 'Hostname and path of the Drupal services endpoint. Include protocol (http:// or https://) and no trailing slash.';
$string['auth_drupalservicescookiedomain_key'] = 'Drupal Session Cookie Domain';
$string['auth_drupalservicescookiedomain'] = 'This is the domain of the drupal session cookie. In most cases this will be the same as "hostname" but in some cases such as when used under a multiple subdomain sso situation, this value will be different. If unsure what to use, leave this field blank.';
$string['auth_drupalservicesendpoint_key'] = 'Endpoint';
$string['auth_drupalservicesendpoint'] = 'Name of the Drupal Service endpoint. Include leading slash but no trailing slash. eg. /service';
$string['auth_drupalservices_remote_user_key'] = 'Remote username';
$string['auth_drupalservices_remote_user'] = 'This is the drupal user used to get index of all users when syncing. Must be able to get unlimited indexes, so set this permission for this user. See docs.';
$string['auth_drupalservices_remote_pw_key'] = 'Remote user password';
$string['auth_drupalservices_remote_pw'] = 'This is the remote user password.';
$string['auth_drupalservicesremove_user_key'] = 'Removed Drupal User';
$string['auth_drupalservicesremove_user'] = 'Specify what to do with internal user accounts during mass synchronization when users were removed from Drupal. Only suspended users are automatically revived if they reappear in Drupal.';
$string['auth_drupalservices_cohorts_key'] = 'Create cohorts';
$string['auth_drupalservices_cohorts'] = 'Create cohorts by looking at a custom view on drupal.';
$string['auth_drupalservices_cohort_view_key'] = 'Path to cohort view';
$string['auth_drupalservices_cohort_view'] = 'The path to the cohort view.';

$string['auth_drupalservicesnorecords'] = 'The Drupal database has no user records!';
$string['auth_drupalservicescreateaccount'] = 'Unable to create Moodle account for user {$a}';
$string['auth_drupalservicesdeleteuser'] = 'Deleted user {$a->name} id {$a->id}';
$string['auth_drupalservicesdeleteusererror'] = 'Error deleting user {$a}';
$string['auth_drupalservicessuspenduser'] = 'Suspended user {$a->name} id {$a->id}';
$string['auth_drupalservicessuspendusererror'] = 'Error suspending user {$a}';
$string['auth_drupalservicesuserstoremove'] = 'User entries to remove: {$a}';
$string['auth_drupalservicescantinsert'] = 'Moodle DB error. Cannot insert user: {$a}';
$string['auth_drupalservicescantupdate'] = 'Moodle DB error. Cannot update user: {$a}';
$string['auth_drupalservicesuserstoupdate'] = 'User entries to update: {$a}';
$string['auth_drupalservicesupdateuser'] ='Updated user {$a}';
