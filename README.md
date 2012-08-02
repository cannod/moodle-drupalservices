moodle-drupalservices
=====================

Moodle plugin to connect to Drupal services

This is a moodle authorisation pluging that allows for SSO between Drupal and Moodle.
All users are maintained on Drupal and Moodle authenticates via REST to the drupal Services module.

TODO:

* Add login URL in auth plugin instead of using alternateloginurl.
* Clean up code.

Overview

This plugin utilised the drupal services module to provide SSO between Drupal and Moodle.
Drupal being the system that does all user management.

Workflow of this plugin.
* Checks to see if there is a valid Drupal session cookie.
* If so, tries to connect to Drupal services and retrieve drupal user info.
* If drupal user is valid, then updates moodle user table by either creating account or updating existing account.
* Apon logout, the user is also logged out from drupal.

Installation instructions.

Drupal
******************************************************************
* Install and enable Drupal services module - http://drupal.org/project/services.
* Insure that you install the REST server module that comes with services.
* To test services, have a look at http://drupal.org/node/783236 and http://drupal.org/node/1699354.

Add a new service.
* At admin/structure/services, click "Add" to create a new service definition (endpoint).
* Give the endpoint a name eg. moodle
* The Server should be REST
* The path to endpoint will form part of the URL so choose something not already existing. eg. mservice
* Choose session authentication
* Click "Save"

Configure the new service
* At admin/structure/services, click on "Edit resources" for the newly created service.
* Expand "system" and select connect.
* Expand "user" and select retrieve, index, login, logout.
* Click "Save"

Drupal user and permisions.

* Create a new drupal user that will be used by Moodle to connect to Drupal. eg. moodle-connect
* Create a new drupal user role. eg. services
* Add the new user to this role.
* At admin/people/permissions, allow the new role the ability to "Perform unlimited index queries" under the Services.

Moodle
******************************************************************
* Extract this plugin to auth/drupalservice. This auth plugin should now be displayed under Manage authentication.
* Go to admin/settings.php?section=manageauths and click on the Settings link to configure Druapl Services.
* Enter the URL to drupal including http://, https://.
* Enter the path to endpoint that you configured in Drupal.
* The remote user the the username of the drupal user you created earlier.
* Enter that users password.
* IMPORTANT!!!! Lock the ID Number field! This field is being used to store the drupal uid for each user.
* I tried locking this field programmatically but had no luck.
* Save changes.
* Enable the Drupal services plugin.

TESTING
******************************************************************
* Logout of moodle
* Login to drupal, then go to moodle and see if you are logged in automatically.
* Your account should be created if not already in Moodle.
* Logout and see if you logged out from drupal as well.

* If all goes well, you can set alternateloginurl under Manage authentication to point to the drupal login page.
* e.g. http://drupalwebisite/user/login. This means that drupal will do all logins.





