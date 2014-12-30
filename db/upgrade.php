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
function xmldb_auth_drupalservices_upgrade($oldversion){
  if($oldversion < 2014111400) {
    // This module has been tracking variables using the wrong name syntax. this retroactively goes
    // back and fixes them to be the proper plugin key upon upgrade
    $config = get_config('auth/drupalservices');
    foreach ((array)$config as $key => $value) {
      set_config($key, $value, 'auth_drupalservices');
    }
    unset_all_config_for_plugin('auth/drupalservices');
  }
  upgrade_plugin_savepoint(true, 2014123000, 'auth', 'drupalservices');
}