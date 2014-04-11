moodle-drupalservices AKA droodle
=====================

Moodle plugin to connect to Drupal services

This is a moodle authorization plugin that allows for SSO between Drupal and Moodle.
All users are maintained on Drupal and Moodle authenticates via REST to the drupal Services module.

In this project we have a branching structure that supports the 3 major supported lines of Moodle currently 2.6, 2.5 and 2.4, as well as tagged releases for this plugin. The branch structure is as follows: [Moodle version]-[plugin version]. Development branches for this plugin are denoted with a ".x-dev" for example 1.x-dev. For users who want stable releases only, you can chose from the Tagged releases. these are denoted with a number instead of x, for example .01, .02 etc.

To chose the proper branch, find your moodle version, and decide which version you wish to use. If you use moodle 2.6.x (the most current), and you would like to use our 1.x development line, make sure you take code from the branch "2.6.x-1.x-dev". If you don't trust the stability risk of a deveopment line, use the highest numbered tag for your moodle and plugin version. For example: 2.6.x-1.00

Configuration instructions have been moved to the github wiki. https://github.com/cannod/moodle-drupalservices/wiki

If you would like to help collaborate on this project, please request using a GitHub issue and we'll add you as a Collaborator.
