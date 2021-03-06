<?php
/**
 * UDPで接続する
 * @author tokushima
 *
 */
class Udp extends Object{
	private $resource;
	private $flags;
	private $address;
	private $port;

	protected function __new__($address,$port,$flags=MSG_DONTROUTE){
		$this->address = $address;
		$this->port = $port;
		$this->flags = $flags;
	}
	protected function __init__(){
		$this->resource = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
		if($this->resource === false) throw new RuntimeException(socket_strerror(socket_last_error()));
	}
	/**
	 * 送信する
	 * @param string $buffer
	 */
	public function send($buffer){
		$result = socket_sendto($this->resource,$buffer,strlen($buffer),$this->flags,$this->address,$this->port);
		if($result === false) throw new RuntimeException('send fail');
	}
	protected function __del__(){
		if(is_resource($this->resource)) socket_close($this->resource);
	}
}