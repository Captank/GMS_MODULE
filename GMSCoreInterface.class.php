<?php

/**
 * Author:
 *  - Captank (RK2)
 *
 * @Instance
 *
 *	@DefineCommand(
 *		command     = 'cgms',
 *		accessLevel = 'all',
 *		description = 'core gms command',
 *		help        = 'gmscore.txt'
 *	)
 */

class GMSCoreInterface {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/** @Inject */
	public $chatBot;
	
	/** @Inject */
	public $db;
	
	/** @Inject */
	public $text;
	
	/** @Inject */
	public $util;
	
	/** @Inject */
	public $buddylistManager;
	
	/** @Inject */
	public $commandManager;
	
	/** @Inject */
	public $accessManager;
	
	private $shopNotFound = 'Error! No shop found for <highlight>%s<end>';
	private $needToRegister = "";
	
	/**
	 * @Setup
	 */
	public function setup() {
		GMSCoreKernel::init($this->moduleName, $this->db, $this->text, $this->util, $this->buddylistManager);
		
		$msg = '<center>'.$this->text->make_chatcmd('I want to register!', '/tell <myname> cgms register').'</center>';
		$msg = $this->text->make_blob('Registration',$msg);
		$msg = 'Error! You need to register first. '.$msg;
		$this->needToRegister = $msg;
	}
	
	
	
	
	/**
	 * This command handler shows a specific item entry.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms contacts$/i")
	 * @Matches("/^cgms contacts (add|rem) (.+)$/i")
	 */
	public function contactCommand($message, $channel, $sender, $sendto, $args) {
		if(($shop = GMSCoreKernel::getShop($sender, true, false)) === NULL) {
			$msg = $this->needToRegister;
		}
		else {
			if(count($args) == 1) {
				$msg = GMSCoreKernel::formatContacts($shop, true);
			}
			else {				
				$args[2] = preg_split("|\\s+|", strtolower($args[2]), -1, PREG_SPLIT_NO_EMPTY);
				$contacts = Array( 0 => Array(), 1 => Array(), 2 => Array(), 3 => Array());
				foreach($args[2] as $contact) {
					$contact = ucfirst(strtolower($contact));
					if($this->chatBot->get_uid($contact)) {
						$cshop = GMSCoreKernel::getShop($contact, false, false);
						if($cshop === NULL) {
							$contacts[0][] = $contact;
						}
						elseif($shop->owner == $cshop->owner) {
							$contacts[1][] = $contact;
						}
						else{
							$contacts[2][] = $contact;
						}
					}
					else {
						$contacts[3][] = $contact;
					}
				}
				
				$add = strtolower($args[1]) == 'add';
				if($add) {
					foreach($contacts[0] as $contact) {
						GMSCoreKernel::addContact($shop, $contact);
					}
				}
				else {
					foreach($contacts[1] as $contact) {
						GMSCoreKernel::removeContact($contact);
					}
				}
				
				$msg = Array();
				if($add && count($contacts[0]) > 0) {
					$tmp = 'Contacts added:';
					foreach($contacts[0] as $contact) {
						$tmp .= '<br><tab>'.$contact;
					}
					$msg[] = $tmp;
				}
				if(count($contacts[1]) > 0) {
					$tmp = ($add ? 'Already your contacts:' : 'Removed contacts:');
					foreach($contacts[1] as $contact) {
						$tmp .= '<br><tab>'.$contact;
					}
					$msg[] = $tmp;
				}
				if(count($contacts[2]) > 0) {
					$tmp = 'Already others contacts:';
					foreach($contacts[2] as $contact) {
						$tmp .= '<br><tab>'.$contact;
					}
					$msg[] = $tmp;
				}
				if(count($contacts[3]) > 0) {
					$tmp = 'Not a player:';
					foreach($contacts[3] as $contact) {
						$tmp .= '<br><tab>'.$contact;
					}
				}
				$msg = implode('<br><br>', $msg);
				if(count($args[2]) < 5) {
					$msg = str_replace('<br><br>', ' ', $msg);
					$msg = str_replace('<br><tab>', ' ', $msg);
				}
				else {
					$msg = $this->text->make_blob(count($args[2]).'/'.count($contacts[0]).' contacts '.($add ? 'added.' : 'removed.'), $msg);
				}
			}
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler shows a specific item entry.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms item (\d+)$/i")
	 */
	public function itemEntryCommand($message, $channel, $sender, $sendto, $args) {
		$entry = GMSCoreKernel::getItemEntry($args[1]);
		if($entry == null) {
			$msg = 'Error! Entry '.$args[1].' not found';
		}
		else {
			$msg = GMSCoreKernel::formatItemEntry($entry);
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler removes a specific item entry.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms rem (\d+)$/i")
	 */
	public function itemRemoveCommand($message, $channel, $sender, $sendto, $args) {
		if(($shop = GMSCoreKernel::getShop($sender, false, false)) === NULL) {
			$msg = $this->needToRegister;
		}
		else {
			$entry = GMSCoreKernel::getItemEntry($args[1]);
			$msg = 'This item doesn\'t exist in your shop!';
			if($entry !== null && $entry->id == $shop->id) {
				GMSCoreKernel::removeItem($entry->itemEntry->id);
				$msg = '<highlight>'$entry->itemEntry->name.'<end> removed';
			}
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler handles the registration process.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms register$/i")
	 */
	public function registerCommand($message, $channel, $sender, $sendto, $args) {
		if(($result = GMSCoreKernel::registerShop($sender)) !== true) {
			$msg = 'Error! You are already registered on '.GMSCoreKernel::getTitle($result).'.';
		}
		else {
			$msg = 'Registration successful.';
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler searches for items.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms search (\d+) (\d+) (.+)$/i")
	 * @Matches("/^cgms search (\d+) (.+)$/i")
	 * @Matches("/^cgms search (.+)$/i")
	 */
	public function searchCommad($message, $channel, $sender, $sendto, $args) {
		$owner = GMSCoreKernel::getShop($sender, false, false);
		$owner =  $owner === NULL ? false : $owner->id;
		$c = count($args);
		$keywords = preg_split("|\\s+|", strtolower($args[$c-1]), -1, PREG_SPLIT_NO_EMPTY);

		switch($c) {
			case 2:
					$items = GMSCoreKernel::itemSearch($keywords, $owner);
				break;
			case 3:
					$items = GMSCoreKernel::itemSearch($keywords, $owner, false, false, $args[1]);
				break;
			case 4:
					if($args[1] < $args[2]) {
						$items = GMSCoreKernel::itemSearch($keywords, $owner, $args[1], $args[2]);
					}
					else {
						$items = GMSCoreKernel::itemSearch($keywords, $owner, $args[2], $args[1]);
					}
				break;
		}
		if($items === NULL) {
			$msg = 'Error! No valid keywords. Keywords have to have a length of at least 3';
		}
		else {
			$msg = GMSCoreKernel::formatItems($items);
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler adds many items with price offer to the store.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms sellall (.*)$/i")
	 */
	public function sellAllItemsCommand($message, $channel, $sender, $sendto, $args) {
		if(($shop = GMSCoreKernel::getShop($sender, false, false)) === NULL) {
			$msg = $this->needToRegister;
		}
		else {
			if(preg_match_all('/<a href="itemref\:\/\/(\d+)\/(\d+)\/(\d+)"\>([^<]+)\<\/a\>/i', $args[1], $matches, PREG_SET_ORDER)) {
				$items = Array(2 => Array(), 1 => Array(), 0 => Array(), -1 => Array());
				foreach($matches as $item) {
					$state = GMSCoreKernel::addItem($shop, $item[1], $item[2], $item[3], 0);
					$items[$state][] = $item[4].' QL'.$item[3];
				}
				$ca = count($items[2]);
				$ct = count($matches);
				
				$msg = Array();
				if(count($items[2])>0) {
					$tmp = 'Items added:';
					foreach($items[2] as $item) {
						$tmp .= '<br><tab>'.$item;
					}
					$msg[] = $tmp;
				}
				
				if(count($items[1])>0) {
					$tmp = 'Items changed:';
					foreach($items[1] as $item) {
						$tmp .= '<br><tab>'.$item;
					}
					$msg[] = $tmp;
				}
				
				if(count($items[0])>0) {
					$tmp = 'Items unaffected:';
					foreach($items[0] as $item) {
						$tmp .= '<br><tab>'.$item;
					}
					$msg[] = $tmp;
				}
				
				if(count($items[-1])>0) {
					$tmp = 'Items forbidden:';
					foreach($items[-1] as $item) {
						$tmp .= '<br><tab>'.$item;
					}
					$msg[] = $tmp;
				}
				
				$msg = $this->text->make_blob($ca.'/'.$ct.' items added.', implode('<br><br>', $msg));
			}
			else {
				$msg = 'Error! No items to add!';
			}
		}
		$sendto->reply($msg);
	}

	/**
	 * This command handler adds an item to the store.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches('/^cgms sell <a href="itemref\:\/\/(\d+)\/(\d+)\/(\d+)"\>([^<]+)\<\/a\>$/i')
	 * @Matches('/^cgms sell <a href="itemref\:\/\/(\d+)\/(\d+)\/(\d+)"\>([^<]+)\<\/a\> (.+)$/i')
	 */
	public function sellItemCommand($message, $channel, $sender, $sendto, $args) {
		if(($shop = GMSCoreKernel::getShop($sender, false, false)) === NULL) {
			$msg = $this->needToRegister;
		}
		else {
			if(count($args) == 5) {
				$price = 0;
			}
			else {
				$price = GMSCoreKernel::parsePrice($args[5]);
			}

			if($price < 0) {
				$msg = "Error! Invalid price '{$args[5]}'.";
			}
			else {
				$state = GMSCoreKernel::addItem($shop, $args[1], $args[2], $args[3], $price);
				switch($state) {
					case 2:
							$msg = 'Item <highlight>'.$args[4].' QL'.$args[3].'<end> added to your shop.';
						break;
					case 1:
							$msg = 'Item <highlight>'.$args[4].' QL'.$args[3].'<end> price changed.';
						break;
					case 0:
							$msg = 'Item <highlight>'.$args[4].' QL'.$args[3].'<end> already for <highlight>'.GMSCoreKernel::priceToString($args[5]).'<end> in shop.';
						break;
					case -1:
							$msg = 'Item <highlight>'.$args[4].' QL'.$args[3].'<end> is marked as invalid item.';
						break;
				}
			}
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler shows shops or categories.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms show$/i")
	 * @Matches("/^cgms show ([a-z0-9-]+)$/i")
	 * @Matches("/^cgms show ([a-z0-9-]+) (\d+)$/i")
	 */
	public function showCommand($message, $channel, $sender, $sendto, $args) {
		$c = count($args);
		$shop = GMSCoreKernel::getShop($c == 1 ? $sender : $args[1]);
	
		if($shop === NULL) {
			$msg = $c == 1 ? $this->needToRegister : sprintf($this->shopNotFound, $args[1]);
		}
		else {
			switch($c) {
				case 1:
				case 2:
						$msg = GMSCoreKernel::formatShop($shop);
					break;
				case 3:
						$cshop = GMSCoreKernel::getShop($sender, false, false);
						$owner = $shop->id == $cshop->id;
						var_dump($cshop->id, $shop->id, $owner);
						
						$msg = GMSCoreKernel::formatCategory($shop, $args[2], $owner);
			}
		}
		$sendto->reply($msg);
	}
}
