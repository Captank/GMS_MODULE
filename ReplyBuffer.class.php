<?php

class ReplyBuffer extends CommandReply {
	public $message;
	public function reply($msg) {
		$this->message = $msg;
	}
}
