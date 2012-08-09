<?php
/**
 * PostageApp API class v0.7
 * Based on http://help.postageapp.com/kb/api/
 *
 * @author ante@sched.org
 */

// TODO - devise a logic to clear required fields after sending a message successfully
//        in order to prevent accidents (e.g. attachments sent mistakenly in a 2nd batch)
// TODO - implement a queue so multiple messages with e.g. same recipients, but different
//        bodies can be sent in one go (plus an iterator)

// **************************************************************************************
// Usage:
//
// $projects = PostageApp::listProjects();
// $mypostage = new PostageApp($projects[0]);
// $mypostage->setRecipients($recipients)->setFrom($us)->setReplyto($us);
// $response = $mypostage->setSubject("Our Fancy Subject")->setBody($body)->customMail();
//
// $mypostage->setProject($projects[1])->setRecipients($recipients2);
// $response = $mypostage->setTemplate("FANCY_PANTS_TEMPLATE")->templateMail();
// if($response->status) $transmissions = $mypostage->getMessageStatus($response->uid);
// **************************************************************************************


class PostageApp {

  const POSTAGE_HOSTNAME = 'https://api.postageapp.com';
  // ******************************************
  // *** ADD YOUR POSTAGE APP PROJECTS HERE ***
  // ******************************************
  private static $__keys = array( "project name" => "project key");
  // ******************************************

  protected $_key = NULL;
  protected $_conf = NULL;

  public $response = FALSE;
  public $error = FALSE;

  protected $_recipients = NULL;
  protected $_from = NULL;
  protected $_replyto = NULL;
  protected $_bodyPlain = NULL;
  protected $_bodyHTML = NULL;
  protected $_subject = NULL;
  protected $_header = array();
  protected $_template = NULL;
  protected $_values = array();
  protected $_attachments = array();

  function __construct($project = NULL) {
    global $conf;
    if($project != NULL) $this->setProject($project);
    if($conf) $this->_conf = $conf;
  }

  public static function listProjects() {
    return array_keys(self::$__keys);
  }



// ***************
// *** SETTERS ***  (arf! arf!)
// ***************
  public function setProject($project) {
    try {
      if(isset(PostageApp::$__keys[$project])) {
        $this->_key = PostageApp::$__keys[$project];
        $this->response = FALSE;
        $this->error = FALSE;
        $this->_header = $this->_values = $this->_attachments = array();
        $this->_recipients = $this->_from = $this->_replyto = $this->_bodyPlain = $this->_bodyHTML = $this->_subject = $this->_template = NULL;        
      } else throw new Exception('PostageApp project does not exist or is not set in lib/PostageApp.php.');
    } catch (Exception $e) {
        echo 'Ooops. ',$e->getMessage(),"<br/>";
    }
    return $this;
  }

  // Recipients are passed as an array or comma separated string.
  // Both versions get internally converted into an array to avoid 
  // divulging e-mail addresses to third parties (by default,
  // PostageApp shows all addresses to all recipients if they're
  // passed as a comma separated string).
  public function setRecipients($recipients) {
    try {
      if(is_array($recipients)) $this->_recipients = $recipients;
      elseif(strpos($recipients,",") !== FALSE) $this->_recipients = explode(",", $recipients);
      else throw new Exception('Invalid recipient list passed. Needs to be an array or comma separated string.');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }
    return $this;
  }

  public function setSubject($subject) {
    $this->_subject = $subject;
    return $this;
  }

  public function setFrom($from) {
    $this->_from = $from;
    return $this;
  }

  public function setReplyto($replyto) {
    $this->_replyto = $replyto;
    return $this;
  }

  // Additional header fields are passed as an array. If a single
  // string is passed, it will get converted into an array. Refer
  // to PostageApp documentation for further info (field names).
  public function setHeader($header) {
    if(is_array($header)) $this->_header = $header;
    else $this->_header = array($header);
    return $this;
  }

  // If only plain text body is passed, it will be used as both,
  // the plain text and HTML version. If both are passed, both
  // will be used.
  public function setBody($bodyPlain, $bodyHTML = NULL) {
    $this->_bodyPlain = $bodyPlain;
    if($bodyHTML !== NULL) $this->_bodyHTML = $bodyHTML;
    else $this->_bodyHTML = $bodyPlain;
    return $this;
  }

  // Accepts slugs set up in PostageApp.
  public function setTemplate($template) {
    $this->_template = $template;
    return $this;
  }

  // Accepts an array of global variable values
  public function setGlobalVars($values) {
    try {
      if(is_array($values) || $values == NULL) $this->_values = $values;
      elseif($values == "") $this->_values = NULL;
      else throw new Exception('Global variable list is not an array!');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }
    return $this;
  }

  // Accepts an array of recipients with subarrays of
  // [variable] => "value" pairs for each recipient. 
  // Recipient addresses are stored in array keys.
  public function setRecipientsWithVars($recipients) {
    try {
      if(is_array($recipients) || $recipients == NULL) {
        foreach($recipients as $recipient=>$variables) {
          foreach($variables as $variable=>$value) {
            $this->_recipients->{$recipient}->{$variable} = $value;          
          }
        }
      } elseif($recipients == "") {
        $this->_values = NULL;
        $this->_recipients = NULL;
      } else {
        throw new Exception('Global variable list is not an array!');
      }
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }
    return $this;
  }

  // Accepts contents of a file or a URL to a file.
  // Can be either a single attachment or an array of
  // attachments. Allows passing mixes of files and URLs.
  // If an array is passed, it has to be of the form:
  // [filename] => ["content_type"] = "content type"
  //            => ["content"] = "content".
  // Single files are three element arrays with keys
  // "filename", "content_type" and "content".
  public function setAttachments($attachments) {
    try {
      if(is_array($attachments) && count($attachments) != 0) {
        if(isset($attachments["filename"])) {
          $this->_attachments[$attachments["filename"]]["content_type"] = $attachments["content_type"];
          if(substr($attachments["content"], 0, 4) == "http" || substr($attachments["content"], 0, 3) == "ftp") {
            $this->_attachments[$attachments["filename"]]["content"] = base64_encode(file_get_contents($attachments["content"]));
          } else {
            $this->_attachments[$attachments["filename"]]["content"] = base64_encode($attachments["content"]);
          }
        } else {
          foreach($attachments as $filename=>$attachment) {
            $this->_attachments[$filename]["content_type"] = $attachment["content_type"];
            if(substr($attachment["content"], 0, 4) == "http" || substr($attachment["content"], 0, 3) == "ftp") {
              $this->_attachments[$filename]["content"] = base64_encode(file_get_contents($attachment["content"]));
            } else {
              $this->_attachments[$filename]["content"] = base64_encode($attachment["content"]);
            }
          }
        }
      } else throw new Exception('Invalid attachments array supplied!');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }
    return $this;
  }
// **********************
// *** END OF SETTERS ***
// **********************


// ***********************************
// *** INTERNAL CURL POST FUNCTION ***
// ***********************************
  protected function _post($api_method, $content) {
    $ch = curl_init(self::POSTAGE_HOSTNAME.'/v.1.0/'.$api_method.'.json');
    curl_setopt($ch, CURLOPT_POSTFIELDS,  $content);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));   
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $output = curl_exec($ch);
    if(curl_error($ch) == "") {
      curl_close($ch);
      json_decode($output);
  
      $res = json_decode($output);
      if($res->response->status == "ok") {
        $this->response = $res;
        $this->error = FALSE;
      } else {
        $this->response = FALSE;
        $this->error = $res->response->message;
      }
    } else {
      curl_close($ch);
      $this->error = "Error accessing PostageApp API!";
      $this->response = FALSE;
    }
  }
// *********************************
// *** END OF CURL POST FUNCTION ***
// *********************************



  public function customMail($uid = NULL) {
    try {
      if($this->_key === NULL) throw new Exception('Project hasn\'t been set!');
      if($this->_replyto === NULL && $this->_from !== NULL) $this->_replyto = $this->_from;
      if($this->_recipients && $this->_from && $this->_replyto && $this->_subject && ($this->_bodyPlain || $this->_bodyHTML)) {
        $content = array(
          'recipients'  => $this->_recipients,
          'headers'     => array_merge($this->_header,
                                       array( 'From' => $this->_from,
                                              'Reply-to' => $this->_replyto,
                                              'Subject' => $this->_subject)),
          'uid'         => ($uid === NULL ? sha1(implode("", $this->_recipients)).time() : $uid),
          'content'     => array( 'text/plain' => $this->_bodyPlain,
                                  'text/html' => $this->_bodyHTML)
        );
        if($this->_values != NULL) $content['variables'] = $this->_values;
        if($this->_attachments != NULL) $content['attachments'] = $this->_attachments;
        $this->_post('send_message', json_encode(array( 'api_key' => $this->_key,
                                                        'arguments' => $content)));
      } else throw new Exception('Required fields for customMail() haven\'t been set.');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }

    if($this->response)
      return $this->response->response->uid;
    else
      return $this->error;
  }

  public function templateMail() {
    try {
      if($this->_key === NULL) throw new Exception('Project hasn\'t been set!');
      if($this->_recipients && $this->_template) {
        $content = array();
        if($this->_from) $content['headers']['From'] = $this->_from;
        if($this->_replyto) $content['headers']['Reply-to'] = $this->_replyto;
        if($this->_header) $content['headers'] = array_merge($this->_header, $content);
        $content = array_merge( $content, 
                                array(
                                  'recipients'  => $this->_recipients,
                                  'uid'         => ($uid === NULL ? sha1(@print_r($this->_recipients,TRUE)).time() : $uid),
                                  'template'    => $this->_template
                              ));
        if($this->_values != NULL) $content['variables'] = $this->_values;
        if($this->_attachments != NULL) $content['attachments'] = $this->_attachments;
        $this->_post('send_message', json_encode(array( 'api_key' => $this->_key,
                                                        'arguments' => $content)));
      } else throw new Exception('Required fields for templateMail() haven\'t been set.');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }

    if($this->response)
      return $this->response->response->uid;
    else
      return $this->error;
  }

  public function getMessageReceipt($uid) {
    try {
      if($this->_key !== NULL) $this->_post('get_message_receipt', json_encode(array( 'api_key' => $this->_key,
                                                                                      'uid' => $uid)));
      else throw new Exception('Project hasn\'t been set!');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }

    if($this->response)
      return $this->response->response->status;
    else
      return $this->error;
  }

  public function getMessages() {
    try {
      if($this->_key !== NULL) $this->_post('get_messages', json_encode(array('api_key' => $this->_key)));
      else throw new Exception('Project hasn\'t been set!');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }
    
    if($this->response)
      return $this->response->data;
    else
      return $this->error;
  }

  public function getMessageStatus($uid) {
    try {
      if($this->_key !== NULL) $this->_post('get_message_transmissions', json_encode(array( 'api_key' => $this->_key,
                                                                                            'uid' => $uid)));
      else throw new Exception('Project hasn\'t been set!');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }

    if($this->response)
      return $this->response->data->transmissions;
    else
      return $this->error;
  }

  public function getMetrics() {
    try {
      if($this->_key !== NULL) $this->_post('get_metrics', json_encode(array('api_key' => $this->_key)));
      else throw new Exception('Project hasn\'t been set!');
    } catch (Exception $e) {
      echo 'Ooops. ',$e->getMessage(),"<br/>";
    }

    if($this->response)
      return $this->response->data->metrics;
    else
      return $this->error;
  }
}
