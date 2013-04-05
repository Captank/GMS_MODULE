<?php

/**
 * Author:
 *  - Captank (RK2)
 *
 * @Instance
 *
 *	@DefineCommand(
 *		command     = 'rgms',
 *		accessLevel = 'all',
 *		description = 'gms command relay',
 *		help        = 'gmscom.txt'
 *	)
 */

class GMSCoreCommunication {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;
	
	/** @Inject */
	public $commandManager;
	
	/** @Inject */
	public $accessManager;

	/**
	 * This command handler handles the relay
	 *
	 * @HandlesCommand("rgms")
	 * @Matches("/^rgms ([a-z]+) (\d+) ([a-z0-9-]+) (cgms .+)$/i")
	 */
	public function relayCommand($message, $channel, $sender, $sendto, $args) {
		$sendto->reply('Relay disabled currently.');
		return;

		$buffer = new ReplyBuffer();
		//$message = 'rgms msg 123 Captank cgms search Potato'
		
		list($genCmd, $genParams) = explode(' ', $args[4], 2);
		$cmd = strtolower($cmd);

		$commandHandler = $this->commandManager->getActiveCommandHandler($genCmd, $args[1], $args[4]);

		//if command doesnt exist, this should never be the case
		if ($commandHandler === null) {
			$sendto->reply("!agms {$args[2]} error - no command handler");
			return;
		}
		
		// if the character doesn't have access
		if ($this->accessManager->checkAccess($args[3], $commandHandler->admin) !== true) {
			$sendto->reply("!agms {$args[2]} error - no access");
			return;
		}

		$msg = false;
		try {
			$syntaxError = $this->commandManager->callCommandHandler($commandHandler, $args[4], $args[1], $args[3], $buffer);

			if ($syntaxError === true) {
				$msg = "!agms {$args[2]} error - syntax error";
			}
		} catch (StopExecutionException $e) {
			throw $e;
		} catch (SQLException $e) {
			$this->logger->log("ERROR", $e->getMessage(), $e);
			$msg = "!agms {$args[2]} error - sql error";
		} catch (Exception $e) {
			$this->logger->log("ERROR", "Error executing '$message': " . $e->getMessage(), $e);
			$msg = "!agms {$args[2]} error - exception thrown";
		}
		if($msg !== false) {
			$sendto->reply($msg);
		}
		else{
			$msg = $buffer->message;
			if(!is_array($msg)) {
				$msg = Array($msg);
			}
			$msg[] = "clean";
			foreach($msg as &$m) {
				$m = "!agms {$args[2]} $m";
			}
			$sendto->reply($msg);
		}
	}
}
