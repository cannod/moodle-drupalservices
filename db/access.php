<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(
  'auth/drupalservices:config' => array(

    'riskbitmask' => RISK_CONFIG,

    'captype' => 'write',
    'contextlevel' => CONTEXT_SYSTEM,
    'archetypes' => array(
    )
  ),
);
