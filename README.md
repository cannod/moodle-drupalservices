moodle-drupalservices
=====================

Moodle plugin to connect to Drupal services

This is a moodle authorization plugin that allows for SSO between Drupal and Moodle.
All users are maintained on Drupal and Moodle authenticates via REST to the drupal Services module.

In this project we support the current major version of Moodle and any of its minor versions.
currently that is Moodle 2, and we have support for 2.4 and higher within Moodle 2.

The branch structure is as follows: [Moodle version]-[plugin version].
Development branches for this plugin are denoted with a ".x-dev" for example 2.x-dev.
For users who want stable releases only, you can chose from the Tagged releases.
these are denoted with a number instead of x, for example 2.x-2.01, 2.x-2.02 etc.

To chose the proper branch, decide which version of the DrupalServices plugin you wish to use.
If you use any version of moodle 2 beyond 2.4, and you would like to use our 2.x development line,
make sure you take code from the branch "2.x-2.x-dev". If you don't trust the stability risk of a development line,
use the highest numbered tag for your moodle and plugin version. For example: 2.x-2.00

Configuration instructions have been moved to the github wiki. https://github.com/cannod/moodle-drupalservices/wiki

If you would like to help collaborate on this project, please request using a GitHub issue and we'll add you as a Collaborator.
