<?php

class Authentication{
  public function Authentication( $userAuth, $realm ){
  
    
    if( !$this->authenticate($userAuth, $realm) ){
      header('HTTP/1.1 401 Unauthorized');
      header('WWW-Authenticate: Digest realm="'.$realm.
             '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
      
      
      throw new Exception('Unauthorized');
    }
    

  }
  
  
  private $username;
  
  public function getUsername(){
    return $this->username;
  }
  
  private function authenticate( $userAuth, $realm ){
    if (empty($_SERVER['PHP_AUTH_DIGEST'])) {

      return false;
    }
    
    
    // analyze the PHP_AUTH_DIGEST variable
    if (!($data = Authentication::http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) ||
      !($userAuth->getUserPass($data['username']) !== false ))
      return false;


    $this->username = $data['username'];
    
    
    // generate the valid response
    $A1 = md5($data['username'] . ':' . $realm . ':' . $userAuth->getUserPass($data['username']));
    $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
    $valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

    if ($data['response'] != $valid_response)
      return false;
      
    return true;
  }

  
  // function to parse the http auth header
  private static function http_digest_parse($txt)
  {
    // protect against missing data
    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
    $data = array();
    $keys = implode('|', array_keys($needed_parts));

    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
      $data[$m[1]] = $m[3] ? $m[3] : $m[4];
      unset($needed_parts[$m[1]]);
    }

    return $needed_parts ? false : $data;
  }



}


?>