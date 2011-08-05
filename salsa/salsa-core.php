<?php
/**
 * Connects to the DIA API to get information about configured actions.  
 * 
 * @author Andrew Marcus
 * @since May 26, 2010
 */
class GFSalsaConnector {
    
    protected static $instance = FALSE;
    
    /**
     * Gets the singleton instance of the Salsa connector.  You must call
     * initialize() before you can call this function.
     * 
     * @return GFSalsaConnector The singleton instance, or false if it has not
     *   yet been initialized.
     */
    public static function instance() {
        return self::$instance;
    }
    
    /**
     * Creates a new API connection and authenticates with the Salsa server.
     * If everything is successful, the organization_KEY will be automatically
     * retrieved.  
     * 
     * You must do this before calling
     * any Salsa functions.
     * 
     * @param string $host The URL of the Salsa server.
     * @param string $user The username.
     * @param string $pass The password.
     * @return GFSalsaConnector The newly-created GFSalsaConnector singleton.
     */
    public static function initialize($host, $user, $pass) {
        self::$instance = new GFSalsaConnector($host, $user, $pass);
        return self::$instance;
    }
    
    /** @var reference $ch The open CURL HTTP connection */
    protected $ch = NULL;
    
    /** @var string $host The URL of the DIA server */
    public $host;
    
    /** @var string $organization_KEY The key of the organization */
    public $organization_KEY;
    
    /** @var array $errors A list of connection errors */
    protected $errors = array();
    
    /**
     * Creates a new connection with the Salsa API.  You should use initialize()
     * to create a singleton instead of calling this function directly. 
     */
    protected function __construct($host, $user, $pass) {
        if (empty($host) || empty($user) || empty($pass)) {
          $this->errors[] = "This page is not configured correctly.";
          return;
        }
        $this->host = str_replace('https://', 'http://', $host);
        
        // Configure the HTTP connection (always POST, maintain cookies)
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($this->ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, '/tmp/cookies_file');
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, '/tmp/cookies_file');
        
        // Authenticate
        $auth = $this->post('/api/authenticate.sjs', array(
          'email' => $user, 'password' => $pass
        ), true);
        
        if (!isset($auth)) {
            return;
        }
        if (isset($auth->error)) {
            $this->errors[] = "We were unable to authenticate with the server.";
            return;
        }
        $attrs = $auth->attributes();
        if (!empty($attrs)) {
            $this->organization_KEY = $attrs['organization_KEY'];
        }
    }
    
    public function addErrors($errors) {
        if (is_string($errors)) {
            $errors = array($errors);
        }
        $this->errors = array_merge($this->errors, $errors);
    }
    
    /**
     * Gets a list of all the errors that have accumulated so far.
     * 
     * @param boolean $reset If this set to false, the errors will be preserved
     *   after this call.  Otherwise, they will be cleared.  
     * @return array<string> A list of error messages, or an empty list if there
     *   have been no errors since the last time the list was reset.
     */
    public function getErrors($reset = true) {
        $out = $this->errors;
        if ($reset) {
            $this->errors = array();
        }
        return $out;
    }
    
    /**
     * Return true if the given XML is valid and has no errors.
     * 
     * @param SimpleXMLElement $xml The XML document to check.
     * @return boolean True if the XML exists and has no errors.
     */
    public function success($xml) {
        return !empty($xml) && !isset($xml->error);
    }
    
    /**
     * Issues an API request to the server.
     *  
     * @param string $path The path on the server, starting with /
     * @param array $params An array of parameters to send in the request
     * @param boolean $ssh If true, SSH will be used in the connection. 
     * @return SimpleXMLElement The XML object returned by the API call.
     */
    function post($path, $params, $ssh = true) {
        $url = $path;
        if (strpos($path, 'http') !== 0) {
            $url = $this->host . $path;
        }
        if ($ssh) {
            $url = str_replace('http://', 'https://', $url);
            curl_setopt($this->ch, CURLOPT_SSLVERSION,3);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        $q = $this->serializeParams($params);
        
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $q);        
        $res = curl_exec($this->ch);
        if (empty($res)) {
            $this->errors[] = "We were unable to connect to the server.";
            return NULL;
        }
        // Convert to an XML object
        $xml = @simplexml_load_string($res);
        if (!isset($xml)) {
            $this->errors[] = 'We got invalid results from the server.';
        }
        elseif (isset($xml->error)) {
            $this->errors[] = $xml->error;
        }
        return $xml;
    }
    
    /**
     * Posts values to a URL and parses the resulting JSON string into
     * an object or array of objects.
     * 
     * @param string $path The path on the server, starting with /
     * @param array $params An array of parameters to send in the request
     * @return array The response, parsed into arrays and hashes
     */
    public function postJson($path, $params) {
        if (strpos($path, 'http') === 0) {
            $url = $path;
        }
        else {
            $url = $this->host . $path;
        }        
        $q = $this->serializeParams($params);
        
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $q);        
        $res = curl_exec($this->ch);
        if (empty($res)) {
            $this->errors[] = "We were unable to connect to the server.";
            return NULL;
        }
        // Convert from a JSON object
        $obj = $this->json_decode($res);
        if (!isset($obj)) {
            $this->errors[] = 'We got invalid results from the server.';
        }
        return $obj;
    }

    /**
     * Serializes the given array of parameters into a valid query string.
     * Use this function instead of http_query_params() when submitting to the
     * Salsa framework, because keys with multiple values should not have array
     * brackets added to them.
     * 
     * The parameters can either be an array of arrays where each inner array
     * contains a single key and value, or it can be an array of key/value pairs
     * where a key with multiple values stores its values in an array. 
     *  
     * @param array $params An array of key/value pairs to post.
     * @return string A url-encoded query string.
     */
    function serializeParams($params) {
        // Serialize the parameters ourselves so that multiple values are not wrapped
        $q = array();
        foreach ($params as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    // If the array is numerically indexed, use the parent key
                    if (is_int($k)) {
                        $k = $key;
                    }
                    $q[] = "$k=" . urlencode($v);
                }
            }
            else {
                $q[] = "$key=" . urlencode($val);
            }
        }
        return implode('&', $q);        
    }
    
    /**
     * Submits a form to the Salsa framework.
     * 
     * @param string $path The path on the server, starting with /
     * @param array $params An array of parameters to send in the request
     * @return string The HTML that was returned from the form submission
     */
    function submitForm($path, $params) {
        $url = $this->host . $path;
        $q = $this->serializeParams($params);
        
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $q);
        $res = curl_exec($this->ch);
        return $res;
    }
    
    /**
     * Counts the number of database objects matching the given query.
     * 
     * @param string $table The name of the table (action, action_content, etc.)
     * @param string|array $conditions A query condition or array of query 
     *   conditions, of the form "action_KEY=355"
     *   
     * @param array $params Any additional parameters to include.
     *   - orderBy: An array of fields to sort by
     *   - limit: The max number of results to return
     *   - offset: The starting offset of the results
     *   - includes: An array of fields to include in the results
     *   
     * @return integer The number of matching objects, or NULL if there was an error.
     */
    public function getCount($table, $conditions = array(), $params = array()) {
        $p = array( 'object' => $table );
        if (!empty($conditions)) {
            $p['condition'] = $conditions;
        }
        if (!empty($params)) {
            $p = array_merge($p, $params);
        }
        $xml = $this->post('/api/getCount.sjs', $p);
        
        if ($this->success($xml)) {
            return (integer)$this->$table->count;
        }
        return NULL;
    }
    
    
    /**
     * Gets one or more database objects.
     * 
     * @param string $table The name of the table (action, action_content, etc.)
     * @param string|array $conditions A query condition or array of query 
     *   conditions, of the form "action_KEY=355"
     *   
     * @param array $params Any additional parameters to include.
     *   - orderBy: An array of fields to sort by
     *   - limit: The max number of results to return
     *   - offset: The starting offset of the results
     *   - includes: An array of fields to include in the results
     *   
     * @param string $className The name of the GFSalsaObject subclass to output.
     *   If this is null, the root SimpleXMLElement will be returned instead.
     *   
     * @return array<GFSalsaObject> The response objects, or null if there was 
     *   an error.
     */
    public function getObjects($table, $conditions = array(), $params = array(), $className = NULL) {
        $p = array( 'object' => $table );
        if (!empty($conditions)) {
            $p['condition'] = $conditions;
        }
        if (!empty($params)) {
            $p = array_merge($p, $params);
        }
        $xml = $this->post('/api/getObjects.sjs', $p);
        
        if ($this->success($xml)) {
            if (empty($className)) {
                return $xml;
            }
            else {
                $out = array();
                if ($xml->$table->item) {
		                foreach ($xml->$table->item as $item) {
		                    $out[] = new $className($item);
		                }
                }
                return $out;
            }
        }
        return NULL;
    }
    
    /**
     * Gets a database object by its key.
     * 
     * @param string $table The name of the table (action, action_content, etc.) 
     * @param integer $key The unique key of the object within the table.
     * 
     * @param string $className The name of the GFSalsaObject subclass to output.
     *   If this is null, the root SimpleXMLElement will be returned instead.
     *   
     * @return GFSalsaObject|SimpleXMLElement The response object, or null if 
     *   there was an error.
     */
    public function getObject($table, $key, $className = NULL) {
        $p = array( 'object' => $table, 'key' => $key );
        $xml = $this->post('/api/getObject.sjs', $p);
        if ($this->success($xml)) {
            if (empty($className)) {
                return $xml;
            }
            return new $className($xml->$table->item[0]);
        }
        return NULL;
    }
    
    /**
     * Saves an object to the Salsa database.
     * 
     * @param string $table The name of the table (action, action_content, etc.) 
     * @param GFSalsaObject|array $object The object to save.  If it has a key parameter, an
     *   existing record will be updated.
     */
    public function saveObject($table, $object) {
        if (is_object($object)) {
            $object = (array)$object;
        }
        $p = array( 'table' => $table );
        $p = array_merge($p, $object);
        $this->post('/save', $p);
    }

    /**
     * When the object is destroyed, close the HTTP connection.
     */
    public function __destruct() {
        if (isset($this->ch)) {
          curl_close($this->ch);
        }
    }
    
    /**
     * Encode JSON objects
     *
     * A wrapper for JSON encode methods. Pass in the PHP array and get a string
     * in return that is formatted as JSON.
     *
     * @param $obj - the array to be encoded
     *
     * @return string that is formatted as JSON
     */
    public function json_encode($obj) {
      // Try to use native PHP 5.2+ json_encode
      // Switch back to JSON library included with Tiny MCE
      if(function_exists('json_encode')){
        return json_encode($obj);
      } else {
        require_once(ABSPATH."/wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");
        $json_obj = new Moxiecode_JSON();
        $json = $json_obj->encode($obj);
        return $json;
      }
    }
    
    /**
     * Encode JSON objects
     *
     * A wrapper for JSON encode methods. Pass in the PHP array and get a string
     * in return that is formatted as JSON.
     *
     * @param $obj - the array to be encoded
     *
     * @return string that is formatted as JSON
     */
    public function json_decode($obj) {
      // Try to use native PHP 5.2+ json_encode
      // Switch back to JSON library included with Tiny MCE
      if(function_exists('json_decode')){
        return @json_decode($obj);
      } else {
        require_once(ABSPATH."/wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");
        $json_obj = new Moxiecode_JSON();
        $json = $json_obj->decode($obj);
        return $json;
      }
    }
}

/**
 * An abstract parent class for all Salsa Objects to extend.
 */
abstract class GFSalsaObject {
    public $object;
    public $key = 0;
    
    function __construct($obj) {
        if ($obj instanceof SimpleXMLElement) {
            foreach ($obj as $tag) {
                $name = $tag->getName();
                $val = (string)$tag;
                $this->$name = $val;
            }
        }
        else {
            foreach ($obj as $k => $v) {
                $this->$k = $v;
            }
        }
    }
    
    /**
     * Saves this object to Salsa.
     */
    public function save() {
        $conn = GFSalsaConnector::instance();
        if ($conn) {
            $conn->saveObject($this->object, $this);
        }
    }
}
