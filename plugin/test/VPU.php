<?php

/* VisualPHPUnit
 *
 * Copyright (c) 2011, Nick Sinopoli <nsinopoli@gmail.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Nick Sinopoli nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace plugin\test;

use lib\File;

require_once 'Autoload.php';
require_once 'Util/Log/JSON.php';

class VPU {

   /**
    *  The collection of tests to run through PHPUnit. 
    *
    *  @var array
    *  @access private
    */
    private $_test_cases = array();

   /**
    *  The sandboxed exceptions.
    *
    *  @var string
    *  @access private
    */
    private $_exceptions = '';

   /**
    *  The list of files to be ignored when creating a stack trace. 
    *
    *  @var array
    *  @access private
    */
    private $_ignore_trace = array(
        'plugin/test/vpu.php',
        'public/index.php',
        'core/controller.php',
        'controller/test.php'
    );
    
   /**
    *  Loads tests from the supplied directory.
    *
    *  @param string $test_dir        The directory containing the tests.
    *  @access public
    *  @return void
    */
    public function __construct($test_dir = null) {
        if ( !is_null($test_dir) ) {
            $this->_set_dir($test_dir);
            $file = new File();
            $file->empty_file(VPU_SANDBOX_FILENAME);
        }
    }

   /**
    *  Converts the JSON from PHPUnit into an array of suites. 
    *
    *  @param string $pu_output        The JSON output from PHPUnit. 
    *  @access public
    *  @return array
    */
    private function _build_suites($pu_output) {
        $results = $this->_parse_output($pu_output);    
        if ( !is_array($results) ) {
            return '';
        }

        $final = $suite = $test = array();
        
        foreach ( $results as $key => $event ) {
            if ( $event['event'] === 'suiteStart' ) {
                if ( isset($suite['tests']) ) {

                    $suite['expand'] = ( $suite['status'] == 'failure' ) ? '-' : '+';
                    $suite['display'] = ( $suite['status'] == 'failure' ) ? 'show' : 'hide';
                    $suite['time'] = 'Executed in ' . $suite['time'] . ' seconds.'; 

                    $final[] = $suite; 
                    $suite = $test = array();
                }

                $suite['status'] = 'success';
                $suite['name'] = $event['suite'];
                $suite['tests'] = array();
                $suite['time'] = 0;
            } elseif ( $event['event'] == 'test' ) {
                $test['status'] = $this->_get_status($event['status'], $event['message']);
                $test['expand'] = ( $test['status'] == 'fail' ) ? '-' : '+';
                $test['display'] = ( $test['status'] == 'fail' ) ? 'show' : 'hide';

                if ( $test['status'] === 'incomplete' && $suite['status'] !== 'failure' && $suite['status'] !== 'skipped' ) {
                    $suite['status'] = 'incomplete';
                } elseif ( $test['status'] === 'skipped' && $suite['status'] !== 'failure' ) {
                    $suite['status'] = 'skipped';
                } elseif ( $test['status'] === 'failure' ) {
                    $suite['status'] = 'failure';
                }
                
                $test['name'] = substr($event['test'], strpos($event['test'], '::') + 2);
                $test['message'] = $this->_get_message($event['message']); 
                $test['message'] .= '<br /><br />Executed in ' . $event['time'] . ' seconds.';
                $suite['time'] += $event['time'];

                $test['variables_message'] = ( isset($event['collected']) ) ? trim($event['collected']) : '';
                $test['variables_display'] = ( $test['variables_message'] ) ? 'show' : 'hide';

                $test['trace_message'] = $this->_get_trace($event['trace']);
                $test['trace_display'] = ( $test['trace_message'] ) ? 'show' : 'hide';
                
                $test['separator_display'] = ( isset($results[$key + 1]) && $results[$key +1 ]['event'] !== 'suiteStart' ); 

                if ( $test['variables_message'] && $test['status'] === 'failure' ) {
                    $test['expand'] = '-';
                    $test['display'] = 'show';
                }

                $suite['tests'][] = $test; 
            }	
                        
        }

        if ( isset($suite['tests']) ) {
            $suite['expand'] = ( $suite['status'] == 'failure' ) ? '-' : '+';
            $suite['display'] = ( $suite['status'] == 'failure' ) ? 'show' : 'hide';
            $suite['time'] = 'Executed in ' . $suite['time'] . ' seconds.'; 

            $final[] = $suite; 
        }
        
        return $final;
    }

   /**
    *  Transforms JSON into a more readable format.
    *
    *  @param string $json        The JSON to be formatted.
    *  @access private
    *  @return string
    */
    private function _format_json($json) {

        $result = '';
        $level = 0;
        $prev_char = '';
        $out_of_quotes = true;
        $length = strlen($json);

        for ( $i = 0; $i <= $length; $i++ ) {
            $char = substr($json, $i, 1);

            if ( $char == '"' && $prev_char != '\\' ) {
                $out_of_quotes = !$out_of_quotes;
            } elseif ( $out_of_quotes && ($char == '}' || $char == ']') ) {
                $result .= "\n";
                $level--;
                $result .= str_repeat("\t", $level);
            }
            
            $result .= $char;

            if ( $out_of_quotes && ($char == ',' || $char == '{' || $char == '[') ) {
                $result .= "\n";
                if ( $char == '{' || $char == '[' ) {
                    $level++;
                }
                
                $result .= str_repeat("\t", $level);
            }
            
            $prev_char = $char;
        }

        return $result;
    }

   /**
    *  Retrieves all of the formatted errors.
    *
    *  @access private
    *  @return string
    */
    private function _get_errors() {
        $errors = file_get_contents(VPU_SANDBOX_FILENAME);
        $file = new File();
        $file->empty_file(VPU_SANDBOX_FILENAME);
        return $errors;
    }

   /**
    *  Retrieves any user-generated debugging messages from a PHPUnit test result. 
    *
    *  @param string $message        The message supplied by VPU's transformed JSON.
    *  @access private
    *  @return string
    */
    private function _get_message($message) {
        if ( !$message ) {
            return '';
        }

        $first = substr($message, 0, strpos($message, 'Failed'));
        $message_rest = str_replace($first, '', $message);
        $first = '<strong>' . $first . '</strong><br />';
        if ( $message_rest ) {
            $message = $first . $message_rest;
        } else {
            $message = $first;
        }
        
        return $message;
    }

    public function get_sandbox() {
        if ( VPU_SANDBOX_ERRORS ) {
            return $this->_exceptions . $this->_get_errors();
        } else {
            return '';
        }
    }

   /**
    *  Retrieves the status from a PHPUnit test result. 
    *
    *  @param string $status        The status supplied by VPU's transformed JSON.
    *  @param string $message       The message supplied by VPU's transformed JSON.
    *  @access private
    *  @return string
    */
    private function _get_status($status, $message) {
        switch ( $status ) {
            case 'pass':
                $status = 'success';
                break;
            case 'error': 
                if ( stripos($message, 'skipped') !== false ) {
                    $status = 'skipped';
                } elseif ( stripos($message, 'incomplete') !== false ) {
                    $status = 'incomplete';
                } else {
                    $status = 'failure';
                }
                break;
            case 'fail':
                $status = 'failure';
                break;
            default:
                $status = '';
                break;
        }

        return $status;
    }

   /**
    *  Retrieves the stack trace from a PHPUnit test result. 
    *
    *  @param string $trace        The message supplied by VPU's transformed JSON.
    *  @access private
    *  @return string
    */
    private function _get_trace($trace) {
        if ( !$trace ) {
            return '';
        }

        $new_trace = array();
        foreach ( $trace as $arr ) {
            $found = false;
            foreach ( $this->_ignore_trace as $ignore ) {
                if ( stripos($arr['file'], $ignore) !== false ) {
                    $found = true;
                    break;
                }
            }

            if ( !$found ) {
                $new_trace[] = $arr;
            }
        }
        
        if ( !empty($new_trace) ) {
            ob_start();
            print_r($new_trace);
            $trace = trim(ob_get_contents());
            ob_end_clean();
        } else {
            $trace = '';
        }

        return $trace;
    }

   /**
    *  Serves as the error handler.  Formats the errors, and then writes them to the sandbox file. 
    *
    *  @param int $err_no            The level of the error raised.
    *  @param string $err_str        The error message.
    *  @param string $err_file       The file in which the error was raised.
    *  @param int $err_line          The line number at which the error was raised.
    *  @access public
    *  @return bool
    */
    public function handle_errors($err_no, $err_str, $err_file, $err_line) {
        $error = array();
        switch ( $err_no ) {
            case E_NOTICE:
                $error['type'] = 'Notice'; 
                break;
            case E_WARNING:
                $error['type'] = 'Warning'; 
                break;
            case E_ERROR:
                $error['type'] = 'Error'; 
                break;
            case E_PARSE:
                $error['type'] = 'Parse'; 
                break;
            default:
                $error['type'] = 'Unknown'; 
                break;
        }
        $error['message'] = $err_str;
        $error['line'] = $err_line;
        $error['file'] = $err_file;
        ob_start(); 
        include 'default/Test/error.html';
        $file = new File(); 
        $file->write(VPU_SANDBOX_FILENAME, ob_get_contents()); 
        ob_end_clean();
        return true;
    }

   /**
    *  Formats exceptions for sandbox use.
    *
    *  @param Exception $exception            The thrown exception.
    *  @access private
    *  @return void
    */
    public function _handle_exception($exception) {
        $error = array(
            'type'    => 'Exception',
            'message' => $exception->getMessage(),
            'line'    => $exception->getLine(),
            'file'    => $exception->getFile()
        );
        ob_start(); 
        include 'default/Test/error.html';
        $this->_exceptions .= ob_get_contents(); 
        ob_end_clean();
    }

   /**
    *  Loads each of the supplied tests. 
    *
    *  @param array|string $tests        The tests to be run through PHPUnit.
    *  @access private
    *  @return array
    */
    private function _load_tests($tests = null) {
        if ( is_null($tests) ) {
            $tests = $this->_test_cases;
        } elseif ( is_string($name) ) {
            $tests = array($tests);
        }

        $loaded_classes = get_declared_classes();

        foreach ( $tests as $test ) {
            require $test;
        }

        // Only return the classes that were just loaded
        return array_diff(get_declared_classes(), $loaded_classes); 
    }

   /**
    *  Parses and formats the JSON output from PHPUnit into an associative array. 
    *
    *  @param string $pu_output        The JSON output from PHPUnit. 
    *  @access private
    *  @return array
    */
    private function _parse_output($pu_output) {
        $results = '';
        $json_elements = $this->_pull($pu_output);
        foreach ( $json_elements as $elem ) {
            $elem = '{' . $elem . '}';
            $pu_output = $this->_replace($elem, '|||', $pu_output);
            $results .= $elem . ',';
        }

        $results = '[' . rtrim($results, ',') . ']';
        $results = str_replace('\n', '', $results);
        $results = str_replace('&quot;', '"', $results);

        if ( VPU_CREATE_SNAPSHOTS ) {
            $file = new File(); 
            $file->create_snapshot($this->_format_json($results), 'vpu_log.json');
        }

        $results = json_decode($results, true);
        
        $pu_output = explode('|||', $pu_output);
        foreach ( $pu_output as $key => $data ) {
            if ( isset($results[$key]) ) {
                $results[$key]['collected'] = $data;
            }
        }

        // Remove the first element
        if ( is_array($results) ) {
            array_shift($results);
        }

        return $results;
    }

   /**
    *  Converts the first nested layer of PHPUnit-generated JSON to an associative array.
    *
    *  @param string $str        The JSON output from PHPUnit. 
    *  @access private
    *  @return array
    */
    private function _pull($str) {
        try {
            $tags = array();
            $nest = 0;
            $start_mark = 0;
            
            $length = strlen($str);
            for ( $i = 0; $i < $length; $i++ ) { 
                $char = $str{$i};
                
                if ( $char == '{' ) {
                    $nest++;
                    if ( $nest == 1 ) {
                        $start_mark = $i;
                    }
                } elseif ( $char == '}' ) {
                    if ( $nest == 1 ) {
                        $tags[] = substr($str, $start_mark + 1, $i - $start_mark - 1);
                        $start_mark = $i;
                    }
                    $nest--;
                }
            }
            
            if ( $nest !== 0 ) {
                throw new \Exception('Unable to parse JSON response from PHPUnit.');
            }

            return $tags;
        } catch (Exception $e) {
            $this->_handle_exception($e);
            return false;
        }
        
    }

   /**
    *  Replaces text within a string. 
    *
    *  @param string $old            The substring to be replaced. 
    *  @param string $new            The replacment string. 
    *  @param string $subject        The string whose contents will be replaced.
    *  @access private
    *  @return string
    */
    private function _replace($old, $new, $subject) {
        try {
            $pos = strpos($subject, $old);
            
            if ( $pos === false ) {
                throw new \Exception('Cannot find tag to replace (old: ' . $old . ', new: ' . htmlspecialchars($new) . ').');
            }

            return substr_replace($subject, $new, $pos, strlen($old));
        } catch (Exception $e) {
            $this->_handle_exception($e);
            return false;
        }
    }
	
   /**
    *  Runs supplied tests through PHPUnit.
    *
    *  @param array|string $tests        The tests to be run through PHPUnit.
    *  @access public
    *  @return array
    */
    public function run($tests = null) {
        $suite = new \PHPUnit_Framework_TestSuite();

        $loaded_tests = $this->_load_tests($tests);
        foreach ( $loaded_tests as $test ) {
            if ( (stripos($test, VPU_TEST_FILENAME) !== false) && (stripos($test, 'PHPUnit_') === false) ) {
                $suite->addTestSuite($test);
            }
        }

        $result = new \PHPUnit_Framework_TestResult;

        $result->addListener(new \PHPUnit_Util_Log_JSON);
        
        ob_start();
        $suite->run($result);
        $results = ob_get_contents();
        ob_end_clean();
        
        return $this->_build_suites($results);
    }

   /**
    *  Iterates through the supplied directory and loads the test files.
    *
    *  @param string $test_dir       The directory containing the tests. 
    *  @access private
    *  @return void
    */
    private function _set_dir($test_dir) {
        try {
            $test_dir = realpath($test_dir);
            if ( !is_dir($test_dir) ) {
                throw new \Exception($test_dir . 'is not a valid directory.');
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($test_dir), \RecursiveIteratorIterator::SELF_FIRST);
                            
            $pattern = '/' . VPU_TEST_FILENAME . '/i';
            while ( $it->valid() ) {
                $filename = $it->getSubPathName();
                if ( !$it->isDot() && preg_match($pattern, $filename) ) {
                    $this->_test_cases[] = $filename;
                }

                $it->next();
            }
        } catch (Exception $e) {
            $this->_handle_exception($e);
            return false;
        }
    }

}

?>
