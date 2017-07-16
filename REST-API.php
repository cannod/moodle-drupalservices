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
// *****************************************************************************************
// defines an object for working with a remote API, not using Drupal API
class RemoteAPI {
  public $gateway;
  public $endpoint;
  public $status;
  public $session;    // the session name (obtained at login)
  public $sessid;     // the session id (obtained at login)
  public $curldefaults = array(
    CURLOPT_HTTPHEADER => array('Accept: application/json'),
    CURLOPT_FAILONERROR => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4,
    CURLOPT_HEADER => 1,
    CURLOPT_SSL_VERIFYPEER => false,

  );
  const RemoteAPI_status_unconnected = 0;
  const RemoteAPI_status_loggedin    = 1;
 
  // *****************************************************************************
  public function __construct( $host_uri, $status = RemoteAPI::RemoteAPI_status_unconnected, $drupalsession=array(), $timeout=60 ) {
    $this->endpoint_uri   = $host_uri.'/';
    $this->curldefaults[CURLOPT_TIMEOUT] = $timeout;
    $this->status  = $status;
    if(isset($drupalsession['session_name'])) {
      $this->session = $drupalsession['session_name'];
      $this->sessid = $drupalsession['session_id'];
    }
    $this->CSRFToken = '';
  }

  // *****************************************************************************
  // return false if we're logged in
  private function VerifyUnconnected( $caller ) {
    if ($this->status != RemoteAPI::RemoteAPI_status_unconnected) {
      return false;
    }
    return true;
  }

  // *****************************************************************************
  // return false if we're not logged in
  private function VerifyLoggedIn( $caller ) {
    if ($this->status != RemoteAPI::RemoteAPI_status_loggedin) {
      return false;
    }
    return true;
  }

  // *****************************************************************************
  // replace these 'resourceTypes' with the names of your resourceTypes
  private function VerifyValidResourceType( $resourceType ) {
    switch ($resourceType) {
      case 'node':
      case 'user':
      case 'thingy':
        return true;
      default: return false;
    }
  }

  // *****************************************************************************
  // Perform the common logic for performing an HTTP request with cURL
  // return an object with 'response', 'error' and 'info' fields.
  private function CurlHttpRequest( $caller, $url, $method, $data, $includeAuthCookie = false, $includeCSRFToken = false ) {

    //In Drupal 8, all REST requests need to specify their requested format.
    $url.=(strstr($url, "?")?"&":"?")."_format=json";
    $ch = curl_init();    // create curl resource
    $curlopts = array();
    switch ($method) {
    case 'POST':
      $curlopts += array(
//        CURLOPT_POST => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $data,
      );

    case 'GET':
    default:
      $curlopts += array(
        CURLOPT_URL => $url,
      );

      // Add the
      if ($includeAuthCookie) {
        $curlopts[CURLOPT_COOKIE] = "{$this->session}={$this->sessid}";
      }

      if ($includeCSRFToken) {
        $curlopts[CURLOPT_HTTPHEADER][] = "X-CSRF-Token: {$this->CSRFToken}";
      }
    }


    curl_setopt_array($ch, $curlopts + $this->curldefaults);

    // Do a quick DNS check to resovle potential caching issues.
    $ip = gethostbyname(parse_url($url,  PHP_URL_HOST));

    // Make the REST request to Drupal.
    debugging("attempting to reach service url: ".$url, DEBUG_DEVELOPER);
    $ret = new stdClass;
    $ret->response_raw = curl_exec($ch); // execute and get response

    // according to: https://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html
    // headers and content are separated by double CRLF.
    list($headers, $body) = explode("\r\n\r\n", $ret->response_raw);

    // Break up the header data into its respective parts per line. This creates
    // two arrays, one with all the keys, and the other with all the values
    preg_match_all("/(.*?): (.*?)\r\n/", $headers, $matches);

    // make a keyed array of headers
    $ret->headers = array_combine($matches[1], $matches[2]);

    // the only header that needs to be digested from here is Set-Cookie.
    if(isset($ret->headers['Set-Cookie'])) {
      //TODO: according to: http://tools.ietf.org/html/rfc6265#section-4.1.2
      //TODO: there's a minor risk of a server returning multiple set-cookie
      //TODO: headers. This probably won't take place in a drupal context, but
      //TODO: this method of handling might need to be refactored.
      //digest all the cookie details into key and value arrays.
      preg_match_all("/(.*?)=(.*?)(?:;|,(?!\s))/", $ret->headers['Set-Cookie'], $matches);

      // combine the cookie details into a keyed array of values.
      $ret->headers['Set-Cookie'] = array_combine($matches[1], $matches[2]);

    }
    $ret->response_raw = $body;

    $ret->error    = curl_error($ch);
    $ret->info     = curl_getinfo($ch);
    curl_close($ch);

    if ($ret->info['http_code'] == 200) {
      $ret->response = json_decode($ret->response_raw);
    }

    return $ret;
  }

  // *****************************************************************************
  // Connect: uses the cURL library to handle system connect 
  public function Connect($debug=false) {

    $callerId = 'RemoteAPI->Connect';
    if (!$this->VerifyLoggedIn( $callerId )) {
      return NULL; // error
    }

    $url = $this->endpoint_uri.'user/login_status';

    $ret = $this->CurlHttpRequest($callerId, $url, 'GET', "", true, true);

    if($debug){
      return $ret;
    }

    if ($ret->info['http_code'] != 200) {
      return NULL;
    }
    elseif ($ret->response_raw !== 0) {
      $url = $this->endpoint_uri.'user/list/me';

      // Note: The user/list/me service endpoint returns an array even though
      // there will only ever be 1 total value at this point in the process.
      $ret = $this->CurlHttpRequest($callerId, $url, 'GET', "", true, true);

      // This is a logged in user!
      return $ret->response[0];
    }

  }  // end of Connect() definition

  // *****************************************************************************
  // Login: uses the cURL library to handle login
  public function Login( $username, $password, $debug=false ) {

    $callerId = 'RemoteAPI->Login';
    if (!$this->VerifyUnconnected( $callerId )) {
      return NULL; // error
    }

    $url = $this->endpoint_uri.'user/login';
    $data = array( 'name' => $username, 'pass' => $password, );
    $data = json_encode($data);

    $ret = $this->CurlHttpRequest($callerId, $url, 'POST', $data, false, false);

    if ($ret->info['http_code'] == 200) { //success!
      $this->status = RemoteAPI::RemoteAPI_status_loggedin;

      $this->CSRFToken = $ret->response->csrf_token;

      $this->session = array_keys($ret->headers['Set-Cookie'])[0];
      $this->sessid = $ret->headers['Set-Cookie'][$this->session];
      //TODO: there is a logout token as well, figure out what it does!
    }

    if($debug){
      return $ret;
    }
    // return true if the query was successful, false otherwise
    return ($ret->info['http_code'] == 200);

  }  // end of Login() definition

  // *****************************************************************************
  // Logout: uses the cURL library to handle logout
  public function Logout() {

    $callerId = 'RemoteAPI->Logout';
    if (!$this->VerifyLoggedIn( $callerId )) {
      return NULL; // error
    }

    $url = $this->endpoint_uri.'/user/logout';
    // Get a CSRF Token for login to be able to login multiple times without logging out.
    //TODO: there is a special CSRF token used only for LOGOUT requests
    //    $this->CSRFToken = $this->GetCSRFToken();

    $ret = $this->CurlHttpRequest($callerId, $url, 'POST', NULL, true, true);
    if ($ret->info['http_code'] != 200) {
      if (!empty($ret->error)) {
        print $ret->error . PHP_EOL;
      }
      return NULL;
    }
    else {
      $this->status = RemoteAPI::RemoteAPI_status_unconnected;
      $this->sessid  = '';
      $this->session = '';
      $this->CSRFToken = '';
      return true; // success!
    }

  }  // end of Login() definition

  // **************************************************************************
  // Get a List of Users based on some pagination options
  // Return an array of resource descriptions, or NULL if an error occurs
  public function ListUsers($last_update, $page, $page_size = 50, $debug = false ) {

    $callerId = 'RemoteAPI->Index';
    if (!$this->VerifyLoggedIn( $callerId )) {
      return NULL; // login error
    }

    $url = $this->endpoint_uri."user/list/all?last_updated={$last_update}&items_per_page={$page_size}&page={$page}";
    $ret = $this->CurlHttpRequest($callerId, $url, 'GET', NULL, true);
    if($debug){
      return (object)array('userlist'=>$ret->response,'info'=>$ret->info);
    }
    return $ret->response;
  }

  // **************************************************************************
  // perform an 'Index' operation on a resource type using cURL.
  // Return an array of resource descriptions, or NULL if an error occurs
  public function Index( $resourceType, $options = NULL, $debug=false ) {

    $callerId = 'RemoteAPI->Index';
    if (!$this->VerifyLoggedIn( $callerId )) {
      return NULL; // login error
    }

    $url = $this->endpoint_uri.'/'.$resourceType . $options;
    $ret = $this->CurlHttpRequest($callerId, $url, 'GET', NULL, true);
    if($debug){
      return (object)array('userlist'=>$ret->response,'info'=>$ret->info);
    }
    return $ret->response;
  }

  // **************************************************************************
  // perform a 'GET' operation on the named resource type and id using cURL.
  public function Get( $resourceType, $resourceId ) {

    $callerId = 'RemoteAPI->Get: "'.$resourceType.'/'.$resourceId.'"';
    if (!$this->VerifyLoggedIn( $callerId )) {
      return NULL; // error
    }
    if (!$this->VerifyValidResourceType($resourceType)) {
      return NULL;
    }

    $url = $this->endpoint_uri . $resourceType.'/'.$resourceId;
    $ret = $this->CurlHttpRequest($callerId, $url, 'GET', NULL, true);
    return $ret->response;
  }

} // end of RemoteAPI object definition using cURL and not Drupal API
?>
