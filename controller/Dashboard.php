<?php

class Dashboard extends ApplicationController
{
    public function index()
    {
        $where = array('username' => 'Derp2');
        $user = new User($where); 
        $entries = $user->Entry;
        foreach ( $entries as $entry )
        {
            $debug = new \lib\Debug();
            $debug->inspect(1, $entry->User);
            die();
        }
        return array("contact_email"=>"test@test.com");
    }   

    public function login()
    {
        if ( $this->_user )
        {
            // TODO: Redirect somewhere
        }

        if ( count($this->_http_get) )    
        {
            $username = $this->_http_get['username'];
            $where = array('username' => $username);
            $user = new User($where); 
            $encrypt = new Encrypt();

            if ( $this->_session->login($username, $this->_http_get['password'], $_SERVER['REMOTE_ADDR'], $user, $encrypt) )
            {
                // TODO: Redirect somewhere
            }
            else
            {
                // TODO: Invalid credentials
            }
        }

        // Display the login page
    }
    
}
