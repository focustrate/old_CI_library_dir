<?php
/* 
 *
 * This class allows for $_GET support without hacking up the CI code
 * see Al James' post on this thread for more info:
 * http://codeigniter.com/forums/viewthread/56389/P0/
 * 
 */
 
 
class MY_Input extends CI_Input {

    function _sanitize_globals()
    {
        $this->allow_get_array = TRUE;
        parent::_sanitize_globals();
    }
    
} 
?>