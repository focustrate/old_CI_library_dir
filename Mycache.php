<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* @file system/application/libraries/Mycache.php
*/
class Mycache
{
	private $enabled;
	private $cache_dir;
	private $cache_type; 
	
	private $memcache;
	private $server_pool;
	private $connected_servers;
	
	public function Mycache()
	{
		$this->enabled = true;
		if($this->enabled) {
			$this->cache_type = 'file'; // 'file' or 'memcache'
			$this->server_pool = array('localhost');
			
			$this->_connect();
		}
	}
	
	private function _connect() 
	{
		switch($this->cache_type) {
			case 'memcache':
				// must turn off error reporting.
				// so memcache can die silently if
				// it can't connect to a server.
				$error_display = ini_get('display_errors');
				$error_reporting = ini_get('error_reporting');

				ini_set('display_errors', "Off");
				ini_set('error_reporting', 0);				
			
				$this->memcache = new Memecache;
			
				foreach($this->server_pool as $server) {
					if($this->memcache->addServer($server, 11211)) {
						array_push($this->connected_servers, $server);
					}	
				}	
			
				// back on again!
				ini_set('display_errors', $error_display);
				ini_set('error_reporting', $error_reporting);
				break;
			case 'file':
				$this->cache_dir = BASEPATH . 'cache/';
				break;
			default: break;
		}	
	}	
	
	public function get($key)
	{
		if(!$this->enabled) { return; }
		
		switch($this->cache_type) {
			case 'memcache':
				if(!$this->connected_servers) { return; }
				return $this->memcache->get($key);			
				break;
			case 'file':
				return $this->_file_get($key);
				break;
			default: break;
		}
		
		return;	
	}
	
	public function set($key, $object, $expire=0)
	{
		if(!$this->enabled) { return; }
		
		switch($this->cache_type) {
			case 'memcache':
				if(!$this->connected_servers) { return; }
				return $this->memcache->set($key, $object, 0, $expire);			
				break;
			case 'file':
				return $this->_file_set($key, $object, $expire);
				break;
			default: break;
		}
		
		return;	
	}
	
	public function delete($key)
	{
		if(!$this->enabled) { return; }
		
		switch($this->cache_type) {
			case 'memcache':
				if(!$this->connected_servers) { return; }
				return $this->memcache->delete($key);			
				break;
			case 'file':
				return $this->_file_delete($key);
				break;
			default: break;
		}
		
		return;	
	}
	
	private function _file_get($key)
	{		
		if(!$this->enabled) { return; }
		
      	$filename = $this->_file_get_name($key);
      	if (!file_exists($filename)) return false;
      	$h = fopen($filename,'r');

      	if (!$h) return false;

      	// Getting a shared lock 
      	flock($h,LOCK_SH);

      	$data = file_get_contents($filename);
      	fclose($h);

      	$data = @unserialize($data);
      	if (!$data) {
         	// If unserializing somehow didn't work out, we'll delete the file
         	unlink($filename);
         	return false;
	    }

      	if (time() > $data[0]) {
         	// Unlinking when the file was expired
         	unlink($filename);
         	return false;
      	}
      
      	return $data[1];    		
	}
	
	private function _file_set($key, $object, $expire=0)
	{
		if(!$this->enabled || !$this->cache_dir) { return; }

	   	// Opening the file in read/write mode
    	$h = fopen($this->_file_get_name($key),'a+');
    	if (!$h) { return; } // silently fail

    	flock($h,LOCK_EX); // exclusive lock, will get released when the file is closed

	    fseek($h,0); // go to the start of the file

    	// truncate the file
    	ftruncate($h,0);

    	// Serializing along with the TTL
    	$object = serialize(array(time()+$expire,$object));
    	if (fwrite($h,$object)===false) {
      		return; // silently fail
    	}
    	fclose($h);

      	return true;
		
	}
	
	private function _file_delete($key)
	{
		if(!$this->enabled) { return; }
      	$filename = $this->_file_get_name($key);
      	if (file_exists($filename)) {
          	return unlink($filename);
      	} 
      	else {
          	return false;
      	}
		
	}
	
	private function _file_get_name($key)
	{
		return $this->cache_dir.'/mycache_'.md5($key);
	}		
	
				
}
?>