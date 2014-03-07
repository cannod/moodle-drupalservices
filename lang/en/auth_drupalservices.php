<?php
$string['pluginname'] = 'Drupal Services';
$string['auth_drupalservicesdescription'] = 'This authentication plugin enables Single Sign-on (SSO) with Drupal. This module will look for a Drupal cookie that represents a valid, authenticated session, and will use it to create an authenticated Moodle session for the same user. The Drupal user will be synchronized with the corresponding user in Moodle. If the user does not yet exist in Moodle, it will be created. Drupal services must be installed and configured on drupal. Please read the README file for installation instructions.';

$string['auth_drupalserviceshostname_key'] = 'Drupal Hostname';
$string['auth_drupalserviceshostname'] = 'Hostname of Drupal services. Include protocol (http:// or https://) and no trailing slash.';
$string['auth_drupalservicescookiedomain_key'] = 'Drupal Session Cookie Domain';
$string['auth_drupalservicescookiedomain'] = 'This is the domain of the drupal session cookie. In most cases this will be the same as "hostname" but in some cases such as when used under a multiple subdomain sso situation, this value will be different. If unsure what to use, leave this field blank.';
$string['auth_drupalservicesendpoint_key'] = 'Endpoint';
$string['auth_drupalservicesendpoint'] = 'Name of the Drupal Service endpoint. Include leading slash but no trailing slash. eg. /service';
$string['auth_drupalservices_remote_user_key'] = 'Remote user.';
$string['auth_drupalservices_remote_user'] = 'This is the drupal user used to get index of all users when syncing. Must be able to get unlimited indexes, so set this permission for this user. See docs.';
$string['auth_drupalservices_remote_pw_key'] = 'Remote user password.';
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
