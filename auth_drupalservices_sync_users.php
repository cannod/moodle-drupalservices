<?php
/** auth_drupal_sync_users.php
 *
 * This script is meant to be called from a system cronjob to sync moodle user
 * accounts with Drupal.
 *
 * Sample cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/auth/db/cli/sync_users.php
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d memory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/course/lib.php');

if (!is_enabled_auth('drupalservices')) {
    echo "Drupal SSO plugin not enabled!";
    die;
}

$drupalservicesauth = get_auth_plugin('drupalservices');
$drupalservicesauth->sync_users(true);
