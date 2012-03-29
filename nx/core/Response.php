<?php

/**
 * NX
 *
 * @author    Nick Sinopoli <NSinopoli@gmail.com>
 * @copyright Copyright (c) 2011, Nick Sinopoli
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace nx\core;

/*
 *  @package core
 */
class Response extends Object {

    public $body = '';

    public $headers = array('Content-Type: text/html; charset=utf-8');

    public $status = 200;

	protected $_statuses = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Time-out',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		416 => 'Requested range not satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Time-out'
	);

    protected function _render() {
        $status = $this->_convert_status($this->status);
        header($status);
        foreach ( $this->headers as $header ) {
            header($header, false);
        }
        $chunks = str_split($this->body, 8192);
        foreach ( $chunks as $chunk ) {
            echo $chunk;
        }
    }

    protected function _convert_status($code) {
        $protocol = 'HTTP/1.1';
        if ( is_numeric($code) ) {
            if ( isset($this->_statuses[$code]) ) {
                return "{$protocol} {$code} {$this->_statuses[$code]}";
            }
        } else {
            $statuses = array_flip($this->_statuses);
            if ( isset($statuses[$code]) ) {
                return "{$protocol} {$status[$code]} {$code}";
            }
        }
        return "{$protocol} 200 OK";
    }

    public function __toString() {
        $this->_render();
        return '';
    }


}

?>
