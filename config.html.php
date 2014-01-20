<?php
global $OUTPUT;

// set to defaults if undefined
if (!isset($config->hostname)) {
    $config->hostname = 'http://';
}

if (!isset($config->endpoint)) {
    $config->endpoint = '/';
}

if (!isset($config->cookie_domain)) {
    $config->cookie_domain = '';
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

if (empty($config->cohorts)) {
    $config->cohorts = 0;
}

if (!isset($config->cohort_view)) {
    $config->cohort_view = '';
}

// does not work.
$config->field_lock_idnumber = 'locked';

$yesno = array( get_string('no'), get_string('yes') );
?>

<pre><code><?php print_r($config); ?></code></pre>

<table cellspacing="0" cellpadding="5" border="0">

    <tr valign="top" class="required">
        <td align="right"><label for="hostname"><?php print_string("auth_drupalserviceshostname_key", "auth_drupalservices") ?></label></td>
        <td>
            <input id="hostname" name="hostname" type="text" size="30" value="<?php echo $config->hostname?>" />
            <?php

            if (isset($err["hostname"])) {
                echo $OUTPUT->error_text($err["hostname"]);
            }

            ?>
        </td>
        <td><?php print_string("auth_drupalserviceshostname", "auth_drupalservices") ?></td>
    </tr>

    <tr valign="top" class="required">
        <td align="right"><label for="endpoint"><?php print_string("auth_drupalservicesendpoint_key", "auth_drupalservices") ?></label></td>
        <td>
            <input id="endpoint" name="endpoint" type="text" size="30" value="<?php echo $config->endpoint?>" />
            <?php

            if (isset($err["endpoint"])) {
                echo $OUTPUT->error_text($err["endpoint"]);
            }

            ?>
        </td>
        <td><?php print_string("auth_drupalservicesendpoint", "auth_drupalservices") ?></td>
    </tr>

    <tr valign="top">
        <td align="right"><label for="cookie_domain">Cookie Domain</label></td>
        <td>
            <input id="cookie_domain" name="cookie_domain" type="text" size="30"
                   value="<?php echo $config->cookie_domain; ?>" />
            <?php
            if (isset($err["cookie_domain"])) {
                echo $OUTPUT->error_text($err["cookie_domain"]);
            }

            ?>
        </td>
        <td>If your Drupal site use custom cookie domain. Config it here.</td>
    </tr>

    <tr valign="top">
        <td align="right"><label for="menuremoveuser"><?php print_string('auth_drupalservicesremove_user_key','auth_drupalservices') ?></label></td>
        <td>
            <?php
            $deleteopt = array();
            $deleteopt[AUTH_REMOVEUSER_KEEP] = get_string('auth_remove_keep','auth');
            $deleteopt[AUTH_REMOVEUSER_SUSPEND] = get_string('auth_remove_suspend','auth');
            $deleteopt[AUTH_REMOVEUSER_FULLDELETE] = get_string('auth_remove_delete','auth');
            echo html_writer::select($deleteopt, 'removeuser', $config->removeuser, false);
            ?>
        </td>
        <td>
            <?php print_string('auth_drupalservicesremove_user','auth_drupalservices') ?>
        </td>
    </tr>
    <tr valign="top" class="required">
        <td align="right">
            <label for="remote_user"><?php print_string('auth_drupalservices_remote_user_key', 'auth_drupalservices') ?></label>
        </td>
        <td>
            <input name="remote_user" id="remote_user" type="text" size="30" value="<?php echo $config->remote_user?>" />
            <?php if (isset($err['remote_user'])) { echo $OUTPUT->error_text($err['remote_user']); } ?>
        </td>
        <td>
            <?php print_string('auth_drupalservices_remote_user', 'auth_drupalservices') ?>
        </td>
    </tr>
    <tr valign="top" class="required">
        <td align="right">
            <label for="remote_pw"><?php print_string('auth_drupalservices_remote_pw_key', 'auth_drupalservices') ?></label>
        </td>
        <td>
            <input name="remote_pw" id="remote_pw" type="password" size="30" value="<?php echo $config->remote_pw?>" />
            <?php if (isset($err['remote_pw'])) { echo $OUTPUT->error_text($err['remote_pw']); } ?>
        </td>
        <td>
            <?php print_string('auth_drupalservices_remote_pw', 'auth_drupalservices') ?>
        </td>
    </tr>
    <tr valign="top">
        <td align="right"><label for="menuupdatecohorts"><?php print_string("auth_drupalservices_cohorts_key", "auth_drupalservices") ?></label></td>
        <td>
            <?php echo html_writer::select($yesno, 'cohorts', $config->cohorts, false); ?>
        </td>
        <td><?php print_string("auth_drupalservices_cohorts", "auth_drupalservices") ?></td>
    </tr>
    <tr valign="top">
        <td align="right">
            <label for="cohort_view"><?php print_string('auth_drupalservices_cohort_view_key', 'auth_drupalservices') ?></label>
        </td>
        <td>
            <input name="cohort_view" id="cohort_view" type="text" size="30" value="<?php echo $config->cohort_view?>" />
            <?php if (isset($err['cohort_view'])) { echo $OUTPUT->error_text($err['cohort_view']); } ?>
        </td>
        <td>
            <?php print_string('auth_drupalservices_cohort_view', 'auth_drupalservices') ?>
        </td>
    </tr>

    <?php

    print_auth_lock_options($this->authtype, $user_fields, get_string('auth_fieldlocks_help', 'auth'), false, false);

    ?>
</table>
