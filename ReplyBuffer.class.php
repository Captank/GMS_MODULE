<?php

class ReplyBuffer implements CommandReply {
	public $message;
	public function reply($msg) {
		$this->message = $msg;
	}
}
