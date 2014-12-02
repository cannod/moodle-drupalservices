<?php

function xmldb_auth_drupalservices_upgrade($oldversion){
  if($oldversion < 2014111400) {
    // This module has been tracking variables using the wrong name syntax. this retroactively goes
    // back and fixes them to be the proper plugin key upon upgrade
    $config = get_config('auth/drupalservices');
    foreach ((array)$config as $key => $value) {
      set_config($key, $value, 'auth_drupalservices');
    }
    unset_all_config_for_plugin('auth/drupalservices');
    upgrade_plugin_savepoint(true, 2014111400, 'auth', 'drupalservices');
  }
}