<?php

/**
 * NX
 *
 * @author    Nick Sinopoli <NSinopoli@gmail.com>
 * @copyright Copyright (c) 2011, Nick Sinopoli
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace nx\core;

use nx\lib\Auth; 
use nx\lib\Data; 
use nx\lib\Meta; 

class Controller extends Object {

   /**
    *  The sanitized data from $_GET.
    *
    *  @var array
    *  @access protected
    */
    protected $_http_get = array();

   /**
    *  The sanitized data from $_POST.
    *
    *  @var array
    *  @access protected
    */
    protected $_http_post = array();

   /**
    *  The controller template.
    *
    *  @var string
    *  @access protected
    */
    protected $_template = DEFAULT_TEMPLATE; 

   /**
    *  The request token.
    *
    *  @var string
    *  @access protected
    */
    protected $_token = null;

   /**
    *  The sanitizers to be used when parsing
    *  request data.  Acceptable sanitizers are:
    *  `key` => `b` for booleans 
    *  `key` => `f` for float/decimals
    *  `key` => `i` for integers
    *  `key` => `s` for strings
    *
    *  @see /nx/lib/Data::sanitize()
    *  @see /nx/core/Controller->sanitize()
    *  @var array
    *  @access protected
    */
    protected $_sanitizers = array();

    protected $_session;
    protected $_user;

    protected $_classes = array(
        'session' => 'app\model\Session', 
        'user'    => 'app\model\User'
    );

   /**
    *  Loads the configuration settings for the controller.
    *  
    *  @access public
    *  @return void
    */
    public function __construct(array $config = array()) {
        $defaults = array(
            'http_get'  => $this->_http_get,
            'http_post' => $this->_http_post,
            'classes'   => $this->_classes
        );
        parent::__construct($config + $defaults);
    }

   /**
    *  Initializes the controller with sanitized http request data and
    *  generates a token to be used to ensure that the next request is valid.
    *
    *  @access protected
    *  @return void
    */
    protected function _init() {
        parent::_init();

        $this->_http_get = $this->sanitize($this->_config['http_get']);
        if ( !$this->_is_valid_request($this->_http_get) ) {
            $this->handle_CSRF();
        }

        $this->_http_post = $this->sanitize($this->_config['http_post']);
        if ( !$this->_is_valid_request($this->_http_post) ) {
            $this->handle_CSRF();
        }

        $this->_token = Auth::create_token($this->classname());

        $session = $this->_config['classes']['session'];
        $this->_session = new $session(); 

        if ( $this->_session->is_logged_in() ) {
            $user = $this->_config['classes']['user'];
            $this->_user = new $user(array('id' => $this->_session->get_user_id()));
            $this->_template = $this->_user->get_template();
        } 
    }

   /**
    *  Calls the controller method, whose return values can then
    *  be passed to and parsed by a view.
    *       
    *  @param string $method       The method.
    *  @param int $id              The id (passed from the URL, useful with query strings like 
    *                              `http://foobar.com/entry/23` or `http://foobar.com/entry/view/23`).
    *  @access public
    *  @return mixed
    */
    public function call($method, $id = null) {
        if ( !method_exists($this, $method) || $this->is_protected($method) ) {
            return false;
        }   

        $results = $this->$method($id);

        if ( is_null($results) || $results === false ) {
            return false;
        }

        $to_view = array(
            'file' => $this->_template . '/' . lcfirst($this->classname()) . '/' . $method . '.html',
            'vars' => $results
        );

        return $to_view;
    }

   /**
    *  Returns the current template.
    *       
    *  @access public
    *  @return string
    */
    public function get_template() {
        return $this->_template;
    }

   /**
    *  Handles CSRF attacks.
    *       
    *  @access public
    *  @return void
    */
    public function handle_CSRF() {
        // TODO: Handle CSRF more elegantly
        die('CSRF attack!');
    }

   /**
    *  Checks if a method is protected. 
    *       
    *  @param string $method       The method.
    *  @access public
    *  @return bool
    */
    public function is_protected($method) {
        return ( in_array($method, Meta::get_protected_methods($this)) );
    }

   /**
    *  Checks that the token submitted with the 
    *  request data is valid.
    *       
    *  @param array $request       The request data.
    *  @access protected
    *  @return bool
    */
    protected function _is_valid_request($request) {
        if ( empty($request) ) {
            return true;
        }
        return Auth::is_token_valid($request, $this->classname());
    }

   /**
    *  Redirects the page.
    *       
    *  @param string $page         The page to be redirected to.
    *  @access public
    *  @return bool
    */
    public function redirect($page) {
        if ( headers_sent() ) {
            echo '<meta content="0; url=' . $page . '" http-equiv="refresh"/>';
        } else {
            header('Location: ' . $page);
        }
        return false;
    }

   /**
    *  Sanitizes data according to the sanitizers defined in $this->_sanitizers. 
    *  If data is an object, the object's sanitize() method will be called.
    *       
    *  @param array $data          The data to be sanitized.
    *  @access public
    *  @return array
    */
    public function sanitize($data) {
        $sanitized = array();
        foreach ( $data as $key => $val ) {
            if ( !is_array($val) ) {
                if ( isset($this->_sanitizers[$key]) ) {
                    $sanitized[$key] = Data::sanitize($val, $this->_sanitizers[$key]);
                }
            } else {
                foreach ( $val as $id => $obj ) {
                    $sanitized[$key][$id] = $obj->sanitize();
                }
            }
        }
        return $sanitized;
    }

}

?>
