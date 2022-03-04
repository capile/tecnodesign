<?php
/**
 * Tecnodesign Mail
 * 
 * Wrapper to other mailers, using a custom API
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
class Tecnodesign_Mail
{
    public static 
        $config=null,
        $mailer='swiftMailer',
        $haltOnError=false;

    protected static $_headers=array(
        'Id'=>false,
        'ReturnPath'=>false,
        'From'=>true,
        'Sender'=>false,
        'To'=>true,
        'Cc'=>false,
        'Bcc'=>false,
        'ReplyTo'=>false,
        'Subject'=>true,
        'Date'=>false,
        'ContentType'=>false,
        'charset'=>true,
    );
    public $headers=null, $contents=null;
    public $sent=false, $saved=false, $errors=null;

    public function __construct($headers=array(), $body=array(), $options=array())
    {
        $this->headers=array();
        $this->contents=array();
        if(!is_array($headers)) {
            $headers = preg_split('/\s*\n/', $headers, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach($headers as $hint=>$header) {
            $this->addHeader($header, $hint);
        }
        if(!is_array($body)) {
            $body = array($body);
        }
        foreach($body as $hint=>$content) {
            $this->addPart($content, $hint);
        }
    }

    public function addPart($content, $hint=false, $cid=null)
    {
        $ct=strtolower($hint);
        $meta=array('attachment'=>false, 'content'=>$content);
        $attachment = false;
        $contentType = ($ct && preg_match('/^[a-z]+\/[a-z\-0-9]+/',$ct))?($ct):(false);
        if($contentType) {
            $meta['content-type'] = $ct;
        } else if($hint && !is_numeric($hint) && is_null($cid)) {
            $cid = $hint;
        } else {
            $meta['content-type']='text/plain';
        }
        if($cid) {
            $meta['attachment'] = true;
            if (substr($cid, 0, 4)=='cid:' && strlen($cid)>4) {
                $meta['id']=$cid;
                $meta['inline']=true;
            } else {
                $meta['id']=$cid;
                $meta['inline']=false;
            }
        }
        $id = (!isset($meta['id']))?($meta['content-type']):($meta['id']);
        $this->contents[$id]=$meta;
    }

    /**
     * Adds headers to the message, can receive arrays or hinted values
     *
     * @param type $header
     * @param type $hint
     */
    public function addHeader($header, $hint=false)
    {
        if ($hint && !is_numeric($hint)) {
            $hint = tdz::camelize($hint, true);
            if (!isset(self::$_headers[$hint])) {
                //$hint = false;
            }
        } else {
            $hint = false;
        }
        if(!is_array($header)) {
            $header = trim($header);
            $header = preg_replace('/[\n\r]+ +/', '', $header);
            $header = preg_split('/[\n\r]+/', $header, -1, PREG_SPLIT_NO_EMPTY);
            if (count($header)>1) {
                foreach($header as $h) {
                    $this->addHeader($h, $hint);
                }
                return true;
            } else {
                $header = $header[0];
            }
        }
        if (!$hint) {
            // search for Header-Name: in the $header
            if(preg_match('/^([a-z\-]+)\:(.*)/i', $header, $m)) {
                $hint = tdz::camelize(ucfirst($m[1]));
                if (!isset(self::$_headers[$hint])) {
                    $hint = false;
                } else {
                    $header = trim($m);
                }
            }
        }
        if (!$hint) {
            $this->error(sprintf(tdz::t('Could not add mail header %s.', 'mail'), $header));
            return false;
        }
        if(method_exists($this, 'set'.$hint)) {
            $m = 'set'.$hint;
            $this->$m($header);
        } else if(method_exists($this, 'add'.$hint)) {
            if(!isset($this->headers[$hint])) {
                $this->headers[$hint]=array();
            }
            $m = 'add'.$hint;
            $this->$m($header);
        } else if(isset($this->headers[$hint])) {
            $this->headers[$hint][]=$header;
        } else {
            $this->headers[$hint]=array($header);
        }
    }

    /**
     * Set text/plain body
     *
     * @param string $body
     */
    public function setTextBody($content)
    {
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/plain';
        }
        $this->addPart($content, 'text/plain');
    }


    /**
     * Set text/html body
     *
     * @param string $body
     */
    public function setHtmlBody($content, $replaceAttachments=null)
    {
        $this->headers['Content-Type'] = 'text/html';
        if(!is_null($replaceAttachments)) {
            $content = $this->findAttachments($content, $replaceAttachments);
        }
        $this->addPart($content, 'text/html');
    }


    public function findAttachments($content, $documentRoot=true)
    {
        if(preg_match_all('#<img[^\>]+src="([^\"]+)"[^\>]*>#i', $content, $m)) {
            if($documentRoot===true) $documentRoot=tdz::getApp()->tecnodesign['document-root'];
            $r=array();
            $s=array();
            $added=array();
            foreach($m[1] as $i=>$file) {
                if(file_exists($f=$documentRoot.'/'.$file)) {
                    $nf='cid:'.md5(realpath($f));
                    //$s[]=$m[0][$i];
                    //$r[]=str_replace($file, $nf, $m[0][$i]);
                    $s[]=$file;
                    $r[]=$nf;
                    if(!isset($added[$nf])) {
                        $this->addPart($f, $nf);
                    }
                }
            }
            $content = str_replace($s, $r, $content);
        }
        return $content;
    }


    public function setEmailHeader($email, $name=false, $hint, $replace=false)
    {
        if($replace) {
            if(isset($this->headers[$hint])) {
                $this->headers[$hint]=array();
            }
        }
        if($L = static::checkEmail($email)) {
            foreach($L as $e=>$n) {
                if(!$n && $name) $n=$name;
                if(!$n) {
                    $this->headers[$hint][$e] = $e;
                } else {
                    $this->headers[$hint][$e] = array($e, $n);
                }
            }
            return true;
        }
    }

    public static function checkEmail($email)
    {
        $r = array();
        if (is_array($email)) {
            if(count($email)==2 && isset($email[0]) && isset($email[1]) && tdz::checkEmail($email[1], false) && !tdz::checkEmail($email[0], false)) {
                $r[$email[1]]=$email[0];
            } else {
                foreach($email as $k=>$e) {
                    if(!is_int($k)) {
                        if(tdz::checkEmail($k, false)) {
                            $r[$k] = $e;
                        } else if(tdz::checkEmail($e, false)) {
                            $r[$e] = $k;
                        }
                    } else if($r2=static::checkEmail($e)) {
                        $r += $r2;
                    }
                }
            }
        } else if(tdz::checkEmail($email, false)) {
            $r[$email]='';
        } else if (preg_match_all("/([^\@\<\>]+\s+)?\<?[\"']?([_a-z0-9-]+[\._a-z0-9-\+\=]*@[a-z0-9\-\.]+)[\"']?\>?[\s\,]*/i", $email, $m)) {
            foreach($m[2] as $k=>$e) {
                if(tdz::checkEmail($e, false)) {
                    $r[$e] = $m[1][$k];
                }
            }
        }
        return $r;
    }

    public function replaceContent($arr)
    {
        if(isset($this->contents['text/html']['content'])) {
            $this->contents['text/html']['content'] = strtr($this->contents['text/html']['content'], $arr);
        }
        if(isset($this->contents['text/plain']['content'])) {
            $this->contents['text/plain']['content'] = strtr($this->contents['text/plain']['content'], $arr);
        }
    }

    /**
     * Add a sender
     * @param string $email sender valid e-mail
     * @param string $name sender name
     */
    public function addFrom ($email, $name = '')
    {
        return $this->setEmailHeader($email, $name, 'From', true);
    }

    /**
     * Add a receiver
     * @param string $email receiver valid e-mail
     * @param string $name receiver name
     */
    public function addTo ($email, $name = '')
    {
        return $this->setEmailHeader($email, $name, 'To');
    }

    /**
     * Add a Carbon Copy (Cc)
     * @param string $email Cc valid e-mail
     * @param string $name Cc name
     */
    public function addCc ($email, $name = '')
    {
        return $this->setEmailHeader($email, $name, 'Cc');
    }

    /**
     * Add a Blind Carbon Copy (Bcc)
     * @param string $email Bcc valid e-mail
     * @param string $name Bcc name
     */
    public function addBcc ($email, $name = '')
    {
        return $this->setEmailHeader($email, $name, 'Bcc');
    }

    /**
     * Set subject
     * @param string $subject
     */
    public function setSubject ($subject)
    {
        unset($this->headers['Subject']);
        $this->headers['Subject'] = $subject;
    }

    /**
     * Set the Reply receiver -- must be unique
     * @param string $email reply receiver valid e-mail
     * @param string $name reply receiver name
     */
    public function setReplyTo ($email, $name = '')
    {
        return $this->setEmailHeader($email, $name, 'ReplyTo');
    }

    /**
     * Set the Sender headers -- must be unique
     * @param string $email sneder valid e-mail
     */
    public function setSender ($email)
    {
        unset($this->headers['Sender']);
        $this->headers['Sender'] = $email;
    }

    /**
     * Set the return-path -- must be unique
     * @param string $email valid e-mail
     * @param string $name name
     */
    public function setReturnPath ($email)
    {
        unset($this->headers['ReturnPath']);
        $this->headers['ReturnPath'] = $email;
    }

    /**
     * Set charset
     * @param string $charset
     */
    public function setCharset ($charset)
    {
        unset($this->headers['charset']);
        $this->headers['charset'] = $charset;
    }

    public function save($id=false)
    {
        if(!$id && isset($this->headers['Id'])){
            $id = $this->headers['Id'];
            if(is_array($id)) {
                $id = array_shift($id);
            }
        }
        if (!$id) {
            $id = uniqid(tdz::slug(tdz::scriptName(true)).'-');
        }
        $dir  = S_VAR.'/mail/';
        if($this->sent){
            if(file_exists($dir.'unsent/'.$id)) {
                unlink($dir.'unsent/'.$id);
            }
            $dir .= date('Ymd').'/';
        } else {
            $dir .= 'unsent/';
        }
        $file = $dir.$id;
        $this->saved = $file;
        if(!tdz::save($file, serialize($this), true, 0600)) {
            $this->saved = false;
        }
        return $this->saved;
    }

    /**
     * Send
     */
    public function send()
    {
        // configure mailer
        if (is_null(self::$config)) {
            $config = false;
            $app = tdz::getApp();
            if ($app) {
                $config = $app->mail;
                if($config){
                    Tecnodesign_Mail::$config=$config;
                    if(isset($config['mailer'])) {
                        Tecnodesign_Mail::$mailer = $config['mailer'];
                    }
                }
            }
            if (!$config) {
                Tecnodesign_Mail::$config = array(
                    'transport'=>'smtp',
                    'server'=>'localhost',
                    'port'=>25,
                    'encryption'=>false,
                    'username'=>false,
                    'password'=>false,
                );
            }
        }
        if (self::$mailer == 'swiftMailer') {
            return $this->sendSwiftMailer();
        }
    }

    protected function sendSwiftMailer()
    {
        /**
         * Load swiftMailer mailer class if is not loaded
         * http://swiftmailer.org/
         */
        if (!class_exists('Swift_SmtpTransport')) {
            $this->error('Could not load mailer package.');
            return false;
        }
        $transport = false;
        $config = self::$config;
        if(isset($config['transport']) && $config['transport']=='smtp') {
            $ssl = (isset($config['encryption']) && $config['encryption'])?('ssl'):(null);
            $transport = new Swift_SmtpTransport($config['server'], $config['port'], $ssl);
            if(isset($config['encryption']) && $config['encryption']) {
                $transport->setEncryption($config['encryption']);
            }
            if(isset($config['username']) && $config['username']) {
                $transport->setUsername($config['username']);
            }
            if(isset($config['password']) && $config['password']) {
                $transport->setPassword($config['password']);
            }
        } else if(isset($config['transport'])) {
            $transport = new Swift_SendmailTransport($config['transport']);
        } else {
            $transport = new Swift_MailTransport();
        }

        //Create a mailer
        $mailer = new Swift_Mailer($transport);

        //Create a message
        $msg = new Swift_Message();

        //Add Subject
        $msg->setSubject($this->headers['Subject']);

        //Add From
        foreach ($this->headers['From'] as $k => $v) {
            if (is_array($v)) {
                $msg->addFrom($v[0],$v[1]);
            } else {
                $msg->addFrom($v);
            }
        }

        //Add To
        if(isset($this->headers['To'])) {
            foreach ($this->headers['To'] as $k => $v) {
                if (is_array($v)) {
                    $msg->addTo($v[0],$v[1]);
                } else {
                    $msg->addTo($v);
                }
            }
        }

        //Add Cc
        if(isset($this->headers['Cc'])) {
            foreach ($this->headers['Cc'] as $k => $v) {
                if (is_array($v)) {
                    $msg->addCc($v[0],$v[1]);
                } else {
                    $msg->addCc($v);
                }
            }
        }

        //Add Bcc
        if(isset($this->headers['Bcc'])) {
            foreach ($this->headers['Bcc'] as $k => $v) {
                if (is_array($v)) {
                    $msg->addBcc($v[0],$v[1]);
                } else {
                    $msg->addBcc($v);
                }
            }
        }

        $replace=array();

        foreach($this->contents as $k => $v) {
            if (in_array($k, array('text/plain', 'text/html'))) {
                if ($msg->getBody()) {
                    $msg->addPart($v['content'],$k);
                } else {
                    $msg->setBody($v['content'],$k);
                }
            } else if(preg_match('/^[a-z]+\/[a-z\-0-9]+/',$k)) {
                $msg->setEncoder(Swift_Encoding::get8BitEncoding());
                $msg->addPart($v['content'],$k);
            } else if(substr($k, 0, 4)=='cid:') {
                $replace[$k]=$msg->embed(Swift_Image::fromPath($v['content']));
            } else {
                if(strlen($v['content'])<500 && file_exists($v['content'])) {
                    $att = Swift_Attachment::fromPath($v['content'], $k);
                } else {
                    $att = new Swift_Attachment($v['content'], $k);
                }
                $msg->attach($att);
            }
            if(count($replace)>0) {
                $msg->setBody(str_replace(array_keys($replace), array_values($replace), $msg->getBody()));
            }
        }

        //Send the message
        try {
            if ($mailer->send($msg)) {
                $this->sent = true;
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        return true;
    }

    protected function error($m)
    {
        if(is_null($this->errors)) {
            $this->errors=array();
        }
        $this->errors[microtime(true)]=$m;
        tdz::log($m);
        if(Tecnodesign_Mail::$haltOnError) {
            throw new Tecnodesign_Exception($m);
        }
    }

    public function getError()
    {
        if(is_null($this->errors)) {
            return false;
        } else {
            return implode("\n", $this->errors);
        }
    }
}