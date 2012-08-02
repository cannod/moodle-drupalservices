moodle-drupalservices
=====================

Moodle plugin to connect to Drupal services

This is a moodle authorisation pluging that allows for SSO between Drupal and Moodle.
All users are maintained on Drupal and Moodle authenticates via REST to the drupal Services module.

TODO:

* Better error checking. When endpoint does not exist.
* Use REST class.
* Include service username and password.
* Remove any hard coded urls etc.
* Add login URL in auth plugin instead of using alternateloginurl.
                        
                         

Detailed instructions.

Drupal

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
* Under permissions admin/people/permissions, 




