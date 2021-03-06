<?php
import("org.rhaco.net.mail.Mail");
import("org.rhaco.net.Socket");
module("SmtpException");
/**
 * Send Mail Transfer Protocol
 * @author SHIGETA Takeshiro
 * @author yabeken
 * @license New BSD License
 * @const mixed[] $host 接続先 hostname,port,timeout
 * @const string[] $account 接続の為のアカウント 
 * @var string $username
 * @var string $password
 * @var string $response @{"set":false}
 * @var integer $response_code @{"set":false}
 */
class Smtp extends Socket{
	protected $username;
	protected $password;
	protected $response;
	protected $response_code;

	protected function __new__($hostname=null,$username=null,$password=null,$port=25,$timeout=5){
		if($hostname === null){
			list($hostname,$port,$timeout) = module_const_array("host",3);
			list($username,$password) = module_const_array("account",2);
			if($port === null) $port = 25;
			if($timeout === null) $timeout = 5;
		}
		parent::__new__($hostname,$port,$timeout);
		$this->username = $username;
		$this->password = $password;
	}
	protected function __del__(){
		$this->logout();
	}
	/**
	 * ログイン
	 * @param string $username
	 * @param string $password
	 * @return boolean
	 */
	public function login($username=null,$password=null){
		if(!$this->is_connected() && !$this->connect()) throw new SmtpException("not connected");
		if($username != null) $this->username = $username;
		if($password != null) $this->password = $password;
		$hostname = (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : "localhost");

		try{
			$this->gets();
			$this->talk("EHLO ".$hostname);
		}catch(SmtpException $e){
			$this->talk("HELO ".$hostname);
		}		
		if(preg_match("/STARTTLS/im",$this->response)) $this->talk("STARTTLS");
		if(preg_match("/AUTH\s(.*?)$/im",$this->response,$matches)){
			$auth = array_map("trim",explode(" ",strtoupper($matches[1])));
			if(in_array("PLAIN",$auth)){
				$this->auth_plain();
			}else if(in_array("LOGIN",$auth)){
				$this->auth_login();
			}else if(in_array("CRAM-MD5",$auth)){
				$this->auth_cram_md5();
			}else if(in_array("DIGEST-MD5",$auth)){
				$this->auth_digest_md5();
			}else{
				throw new SmtpException(sprintf("unknown auth type [%s]",$matches[1]));
			}
		}
		return true;
	}
	private function auth_plain(){
		$key = base64_encode($this->username."\0".$this->username."\0".$this->password."\0");
		$this->talk("AUTH PLAIN ".$key);
	}
	private function auth_login(){
		$this->talk("AUTH LOGIN %s %s",$this->username,$this->password);
	}
	private function auth_cram_md5(){
		$this->talk("AUTH CRAM-MD5");
		$r = explode(" ",$this->response);
		$this->talk(base64_encode(sprintf("%s %s",$this->username,md5(base64_decode($r[1]).$this->password))));
	}
	private function auth_digest_md5(){
		//TODO
		throw new SmtpException("not support");
	}
	/**
	 * ログアウト
	 */
	public function logout(){
		if($this->is_connected()) $this->talk("QUIT");
		$this->close();
	}
	/**
	 * コマンド送信
	 * @param string $message
	 * @return boolean
	 */
	private function talk($message){
		$this->response = $this->response_code = null;
		$this->write($message."\r\n");
		$this->gets();
	}
	private function gets(){
		while(true){
			$line = $this->read_line();

			$this->response .= $line;
			if(substr($line,3,1) === "-") continue;
			
			$this->response_code = intval(substr($line,0,3));
			if($this->response_code >= 200 && $this->response_code < 400){
				return;
			}else if($this->response_code >= 400 && $this->response_code < 600){
				throw new SmtpException("invalid response ".$this->response_code.", ".$line);
			}
			if($this->is_eof()) break;
		}
		throw new SmtpException("unknown response ".$this->response);		
	}
	
	/**
	 * メール送信
	 * @param Mail $mail
	 */
	public function send_mail(Mail $mail){
		if(!$this->is_connected() && !$this->login()) new SmtpException("not connected");
		$this->talk(sprintf("MAIL FROM: <%s>",$mail->from()));
		foreach($mail->to() as $email => $addr){
			$this->talk(sprintf("RCPT TO: <%s>",$email));
		}
		foreach($mail->cc() as $email => $addr){
			$this->talk(sprintf("RCPT TO: <%s>",$email));
		}
		foreach($mail->bcc() as $email => $addr){
			$this->talk(sprintf("RCPT TO: <%s>",$email));
		}
		$this->talk("DATA");
		$this->talk(preg_replace('/^\.(\r?\n)/m','..$1',$mail->manuscript()).".");
	}
}