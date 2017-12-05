<?php
/**
 * FogBugz PHP API
 *
 * Built against FB API 8
 *
 * @author  Craig Davis <craig@there4development.com>
 * @created 1/15/2011
 * @link    https://github.com/there4/fogbugz-php-api
 * @see     http://fogbugz.stackexchange.com/fogbugz-xml-api
 * @license MIT http://www.opensource.org/licenses/mit-license.php
 */

namespace There4\FogBugz;

/**
 * FogBugz API Wrapper
 *
 * Interface with FobgBugz API
 *
 * Example 1: Sample (w/o exception handling)
 *
 *   $fogbugz = new FogBugz\Api(
 *       'username@example.com',
 *       'password',
 *       'http://example.fogbugz.com'
 *   );
 *   $fogbugz->logon();
 *   $fogbugz->startWork(array(
 *     'ixBug' => 23442
 *   ));
 *   $fogbugz->logoff();
 *
 *  Example 2) Using API token
 *   $fogbugz = new FogBugz\Api(
 *       '',
 *       '',
 *       'http://example.fogbugz.com'
 *   );

 *   $fogbugz->setToken('your_token');
 *   $fogbugz->startWork(array(
 *     'ixBug' => 23442
 *   ));
 *
 * @author Craig Davis <craig@there4development.com>
 * @author Svetoslav (Slavi) Marinov <slavi@orbisius.com> (contributor)
 */
class Api
{
    /**
     * Url to the FogBugz site, http:[yoursite].fogbugz.com
     * @var string
     */
    public $url = '';

    /**
     * Path to the FogBugz api script
     * @var string
     */
    public $path = 'api.asp';
    
    /**
     * Username for the site
     * @var string
     */
    public $user = '';

    /**
     * User password for the site
     * @var string
     */
    public $pass = '';

    /**
     * Curl interface with FB specific settings
     * @var string
     */
    public $curl = '';

    /**
     * API token generated from the bogbugz site. So we don't use email/pwd combo.
     * @var string
     */
    public $token = '';
    
    /**
     * Stores the xml buff from the last request
     * @var str
     */
    public $last_result = '';

    /**
     * Constructor
     *
     * @param string $user username for fogbugz connection (default: '')
     * @param string $pass password for fogbugz connection (default: '')
     * @param string $url  base url for fogbugz (default: '')
     * @param string $path path to api script (default: '')
     *
     * @return void
     */
    public function __construct($user = '', $pass = '', $url = '', $path = '')
    {
        // if the values are not empty, we'll assign them to our matching properties
        $args = array('user', 'pass', 'url', 'path');
        foreach ($args as $arg) {
            if (!empty($$arg)) {
                $this->$arg = $$arg;
            }
        }

        // make sure there is a / between the url and the path
        if ('/' != substr($this->url, -1) && '/' != substr($this->path, 0, 1)) {
            $this->url .= "/";
        }

        // init our curl object here
        $this->curl = new Curl();
    }

    /**
     * Respond to FogBugz API Calls
     *
     * @param string $name      FogBugz API command name, see docs ?cmd=
     * @param array  $arguments first argument contains
     *                          an array of params for FogBugz, ie:
     *                          ixBug, sEmail, ixProject, ixPerson
     *
     * @return SimpleXMLElement containing the result from FB
     */
    public function __call($name, $arguments)
    {
        // if the anon method is called without arguments, we won't send any
        // along, it $fb->stopWork();
        $parameters
            = isset($arguments[0])
            ? $arguments[0]
            : array();

        $res = $this->_request($name, $parameters);

        return $res;
    }

    /**
     * Logon to FogBugz API and store the authentication token
     *
     * You don't have to explicitely call this, unless you want a new
     * token, the constructor runs it automatically
     *
     * @return void
     */
    public function logon()
    {
        try {
            // make the initial logon request to get a token
            // that we use in subsequent requests
            $xml = $this->_request(
                'logon',
                array(
                    'email'    => $this->user,
                    'password' => $this->pass
                )
            );
            // store this token for use later
            $this->setToken($xml->token);
        } catch (ApiError $e) {
            $message
            = "Login Error. "
            . "Please check the url, username and password. Error: "
            . $e->getMessage();
            throw new ApiLogonError($message, 0);
        }

        return true;
    }

    /**
     * Logoff and unset our authentication token
     *
     * @return void
     */
    public function logoff()
    {
        $this->_request('logoff');
        $this->setToken('');
    }

    /**
     * Send request to FogBugz
     *
     * Internal handler to communicate to FB
     *
     * @param string $command FogBugz command, ?cmd=
     * @param array  $params  fogbugz parameters (default: array())
     *
     * @return SimpleXMLElement containing the result from FB
     */
    protected function _request($command, array $params = array())
    {
        // the logon command generates the token
        if ('logon' != $command) {
            $params['token'] = $this->token;
        }
        
        // add the command to the get request
        $params['cmd'] = $command;
        $url = $this->url . $this->path;

        // make the request and throw an api exception if we detect an error
        try {
            $result = $this->curl->fetch($url, $params);
            $this->setLastBuff($result);
            
            $xml    = new \SimpleXMLElement($result, LIBXML_NOCDATA);
            
            if (isset($xml->error)) {
                $code    = (string) $xml->error['code'];
                $message = (string) $xml->error;
                throw new ApiError($message, $code);
            }
        } catch (CurlError $e) {
            throw new ApiError($e->getMessage(), 0);
        }

        return $xml; // return the SimpleXMLElement object
    }

    /**
     * Getter for token.
     * @return str $token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Setter for token
     * @param str $token
     * @return str $token
     */
    public function setToken($token = '')
    {
        $this->token = (string) $token;
        return $this->getToken();
    }

    /**
     * Getter for last result (string xml buffer).
     * @return str $token
     */
    public function getLastBuff()
    {
        return $this->last_result;
    }

    /**
     * Setter for last result
     * @param str $buf
     */
    public function setLastBuff($buf)
    {
        $this->last_result = (string) $buf;
    }
}

/* End of Api.php */
