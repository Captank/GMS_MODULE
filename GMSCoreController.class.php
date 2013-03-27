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
 *	@DefineCommand(
 *		command     = 'rgms',
 *		accessLevel = 'all',
 *		description = 'gms command relay',
 *		help        = 'gmscore.txt'
 *	)
 */
class GlobalShopCoreController {

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
	
	private $shopNotFound = 'Error! No shop found for <highlight>%s<end>';
	private $needToRegister = "You need to register first.";
	
	/**
	 * @Setup
	 */
	public function setup() {
		$this->db->loadSQLFile($this->moduleName, "gms");
//		var_dump($this->getShop(1), $this->getShop("Captank"), $this->getShop("Potato"), $this->getShop("xD"));
//		var_dump($this->itemSearch(Array('pant')), $this->itemSearch(Array('pant'),50,300), $this->itemSearch(Array('pant'), false, false, 105));
//		$shop = $this->getShop(1, false, false);
//		var_dump($this->getShopItems($shop->id, 1));
//		var_dump($this->formatContacts($this->getShop(1)));
//		var_dump($this->getShop('xD', false, false));
		var_dump($this->formatItems($this->itemSearch(Array('pant'))));
	}
	
	/**
	 * This command handler handles the registration process.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms register$/i")
	 */
	public function gmsRegisterCommand($message, $channel, $sender, $sendto, $args) {
		if(($shop = $this->getShop($sender, false, false)) !== NULL) {
			$msg = 'Error! You are already registered on '.$this->getTitle($shop->owner).'.';
		}
		else {
			$sql = <<<EOD
INSERT INTO `gms_shops`
    (`owner`)
VALUES
    (?)
EOD;
			$this->db->exec($sql, $sender);
			$msg = 'Registration successful.';
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler shows shops or categories.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms show$/i")
	 * @Matches("/^cgms show (.*)$/i")
	 * @Matches("/^cgms show (.*) (\d+)$/i")
	 */
	public function gmsShowCommand($message, $channel, $sender, $sendto, $args) {
		$c = count($args);
		$shop = $this->getShop($c == 1 ? $sender : $args[1]);
	
		if($shop === NULL) {
			$msg = $c == 1 ? $this->needToRegister : sprintf($this->shopNotFound, $args[1]);
		}
		else {
			switch($c) {
				case 1:
				case 2:
						$msg = $this->formatShop($shop);
					break;
				case 3:
						$msg = $this->formatCategory($shop, $args[2]);
			}
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler searches for items.
	 *
	 * @HandlesCommand("cgms")
	 * @Matches("/^cgms search (\d+) (\d+) (.+)$/i")
	 * @Matches("/^cgms search (\d+) (.+)$/i"
	 * @Matches("/^cgms search (.+)$/i"
	 */
	public function gmsSearchCommad($message, $channel, $sender, $sendto, $args) {
		$owner = $this->getShop($sender, false, false);
		$owner =  $owner === NULL ? false : $owner->id;
		$c = count($args);
		$keywords = preg_split("|\\s+|", strtolower(array_pop($args[1])), -1, PREG_SPLIT_NO_EMPTY);
		switch($c) {
			case 2:
					$items = itemSearch($keywords, $owner);
				break;
			case 3:
					$items = itemSearch($keywords, $owner, false, false, $args[1]);
				break;
			case 4:
					$items = itemSearch($keywords, $owner, $args[1], $args[2]);
				break;
		}
		if($items === NULL) {
			$msg = 'Error! No valid keywords. Keywords have to have a length of at least 3';
		}
		else {
			$msg = $this->formatItems($items);
		}
		$sendto->reply($msg);
	}
	
	/**
	 * This command handler handles the relay
	 *
	 * @HandlesCommand("rgms")
	 * @Matches("/^rgms ([a-z]+) (\d+) ([a-z0-9-]+) (gms .+)$/i")
	 */
	public function relayCommand($message, $channel, $sender, $sendto, $args) {
		$sendto->reply('Relay disabled currently.');
		return;
		$buffer = new ReplyBuffer();
		//$message = 'rgms Kartoffel 123 Captank gms search Potato'
		
		list($genCmd, $genParams) = explode(' ', $args[4], 2);
		$cmd = strtolower($cmd);

		$commandHandler = $this->commandManager->getActiveCommandHandler($genCmd, $args[1], $args[4]);

		//if command doesnt exist, this should never be the case
		if ($commandHandler === null) {
			$sendto->reply("!agms {$args[2]} error");
			return;
		}
		
		// if the character doesn't have access
		if ($this->accessManager->checkAccess($args[3], $commandHandler->admin) !== true) {
			$sendto->reply("!agms {$args[2]} error");
			return;
		}

		$msg = false;
		try {
			$syntaxError = $this->callCommandHandler($commandHandler, $args[4], $args[1], $args[3], $buffer);

			if ($syntaxError === true) {
				$msg = "!agms {$args[2]} error";
			}
		} catch (StopExecutionException $e) {
			throw $e;
		} catch (SQLException $e) {
			$this->logger->log("ERROR", $e->getMessage(), $e);
			$msg = "!agms {$args[2]} error";
		} catch (Exception $e) {
			$this->logger->log("ERROR", "Error executing '$message': " . $e->getMessage(), $e);
			$msg = "!agms {$args[2]} error";
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
	
	/**
	 * Get shop data.
	 *
	 * @param mixed $identifier - either the owner/contact name or the shop id
	 * @param boolean $contacts - defines if contacts will be fetched, default true
	 * @param boolean $items - defines if items will be fetched, default true
	 * @return array - the structured array with shop data, if no shop for $identifier was found NULL
	 */
	public function getShop($identifier, $contacts = true, $items = true) {
		if(preg_match("~^\d+$~",$identifier)) {
			$sql = <<<EOD
SELECT
	`gms_shops`.`id`,
	`gms_shops`.`owner`
FROM
	`gms_shops`
WHERE
	`gms_shops`.`id` = ?
LIMIT 1
EOD;
			$shop = $this->db->query($sql, $identifier);
			if(count($shop) != 1) {
				return null;
			}
		}
		else {
			$sql = <<<EOD
SELECT DISTINCT
	`gms_shops`.`id`,
	`gms_shops`.`owner`
FROM
	`gms_shops`,
	`gms_contacts`
WHERE
	(`gms_shops`.`owner` = ?  OR `gms_contacts`.`character` = ? ) AND `gms_shops`.`id` = `gms_contacts`.`shopid`
LIMIT 1
EOD;
			$identifier = ucfirst(strtolower($identifier));
			$shop = $this->db->query($sql, $identifier, $identifier);
			if(count($shop) != 1) {
				return null;
			}
		}
		$shop = $shop[0];

		if($contacts) {
			$shop->contacts = $this->getShopContacts($shop->id);
		}
		else {
			$shop->contacts = null;
		}
		if($items) {
			$shop->items = $this->getShopItems($shop->id);
		}
		else {
			$shop->items = null;
		}
		return $shop;
	}
	
	/**
	 * Get items data for a shop
	 *
	 * @param int $shopid - the shop id
	 * @param mixed $category - int for the category id, false for all categories
	 * @return array - array of DBRows for the item data
	 */
	public function getShopItems($shopid, $category = false) {
		$data = Array($shopid);
		
		if($category !== false) {
			$sql = " AND `gms_item_categories`.`category` = ?";
			$data[] = $category;
		}
		else {
			$sql = '';
		}
		$sql = <<<EOD
SELECT
	`gms_items`.`id`,
	`gms_items`.`lowid`,
	`gms_items`.`highid`,
	`gms_items`.`ql`,
	`aodb`.`name`,
	`aodb`.`icon`,
	`gms_items`.`price`,
	`gms_item_categories`.`category`
FROM
	`gms_items`
		LEFT JOIN
	`aodb` ON `gms_items`.`lowid` = `aodb`.`lowid` AND `gms_items`.`highid` = `aodb`.`highid`
		LEFT JOIN
	`gms_item_categories` ON `gms_item_categories`.`lowid` = `gms_items`.`lowid` AND `gms_item_categories`.`highid` = `gms_items`.`highid`
WHERE
	`gms_items`.`shopid` = ?$sql
ORDER BY
	`gms_item_categories`.`category` ASC, `aodb`.`name` ASC, `gms_items`.`ql` ASC, `gms_items`.`price` ASC
EOD;
		return $this->db->query($sql, $data);
	}
	
	/**
	 * Get contact characters for an shop
	 *
	 * @param int $shopid - the shop id
	 * @return array - array of DBRows for the contact data
	 */
	public function getShopContacts($shopid) {
		$sql = <<<EOD
SELECT
	`gms_contacts`.`character`
FROM
	`gms_contacts`
WHERE
	`gms_contacts`.`shopid` = ?
ORDER BY
	`gms_contacts`.`character` ASC
EOD;
		return $this->db->query($sql, $shopid);
	}
	
	/**
	 * Search for an item.
	 *
	 * @param array $keywords - array of keywords
	 * @param mixed $minQL - int for min ql, false for inactive
	 * @param mixed $maxQL - int for max ql, false for inactive
	 * @param mixed $exactQL - int for for exact ql, false for inactive
	 * @return array - array of DBRow for found items, null if no valid keywords
	 */
	public function itemSearch($keywords, $owner = false, $minQL = false, $maxQL = false, $exactQL = false) {
		$data = Array();
		$sqlPattern = Array();
		foreach($keywords as $keyword) {
			if(strlen($keyword) > 2) {
				$data[] = "%$keyword%";
				$sqlPattern[] = "`aodb`.`name` LIKE ?";
			}
		}
		
		if(count($data) == 0) {
			return null;
		}
		
		if($owner !== false) {
			$sqlPattern[] = '`gms_items`.`shopid` != ?';
			$data[] = $owner;
		}

		if($minQL !== false && $maxQL !== false) {
			$data[] = $minQL;
			$data[] = $maxQL;
			$sqlPattern[] = "`gms_items`.`ql` >= ?";
			$sqlPattern[] = "`gms_items`.`ql` <= ?";
		}
		elseif($exactQL !== false) {
			$data[] = $exactQL;
			$sqlPattern[] = "`gms_items`.`ql` = ?";
		}
		$sql = implode(" AND ", $sqlPattern);
		$sql = <<<EOD
SELECT
	`gms_items`.`id`,
	`gms_items`.`shopid`,
	`gms_items`.`lowid`,
	`gms_items`.`highid`,
	`gms_items`.`ql`,
	`gms_items`.`price`,
	`aodb`.`icon`,
	`aodb`.`name`,
	`gms_item_categories`.`category`
FROM
	`gms_items`
		LEFT JOIN
    `aodb` ON `gms_items`.`lowid` = `aodb`.`lowid` AND `gms_items`.`highid` = `aodb`.`highid`
		LEFT JOIN
	`gms_item_categories` ON `gms_items`.`lowid` = `gms_item_categories`.`lowid` AND `gms_items`.`highid` = `gms_item_categories`.`highid`
WHERE
	$sql
ORDER BY
	`gms_item_categories`.`category` ASC, `aodb`.`name` ASC, `gms_items`.`ql` ASC, `gms_items`.`price` ASC
LIMIT 40
EOD;
		return $this->db->query($sql, $data);
	}
	
	/**
	 * Get all categories.
	 *
	 * @return array - returns an array of categories, array index is category id and array value is category name
	 */
	public function getCategories() {
		$sql = <<<EOD
SELECT
	`gms_categories`.`id`,
	`gms_categories`.`name`
FROM
	`gms_categories`
EOD;
		$data = $this->db->query($sql);
		
		$result = Array();
		foreach($data as $category) {
			$result[$category->id] = $category->name;
		}
		return $result;
	}
	
	/**
	 * Format shop for messages.
	 *
	 * @params array $shop - the shop array structur
	 * @return string - the formated message blob
	 */
	public function formatShop($shop) {
		$categories = $this->getCategories();
		
		$cats = Array();
		foreach($shop->items as $item) {
			if(isset($cats[$item->category])) {
				$cats[$item->category]++;
			}
			else {
				$cats[$item->category] = 1;
			}
		}
		
		if(count($cats) == 0) {
			$cats[] = '<tab>This shop is empty at the moment.';
		}
		else {
			foreach($cats as $cid => &$cat) {
				$cat = sprintf('<tab>%s (%d %s)', $categories[$cid], $cat, ($cat > 1 ? 'items' : 'item'));
			}
		}
		$cats[] = $this->formatContacts($shop);
		return $this->text->make_blob($this->getTitle($shop), implode('<br><br>',$cats));
	}
	
	/**
	 * Format shop category for messages.
	 *
	 * @params array $shop - the shop array structur
	 * @params int $category - the category id
	 * @return string - the formated message blob
	 */
	public function formatCategory($shop, $category) {
		$categories = $this->getCategories();
		if(!isset($categories[$category])) {
			return "Error! Invalid id '$category'";
		}
		$items = Array(); //$item["lowid/highid"] = array (ql => item)
		foreach($shop->items as $item) {
			if($item->category == $category) {
				$idx = $item->lowid.'/'.$item->highid;
				if(!isset($items[$idx])) {
					$items[$idx] = Array($item->ql => $item);
				}
				else {
					$items[$idx][$item->ql] = $item;
				}
			}
		}
		
		$out = Array();
		foreach($items as $item) {
			$tmp = Array();
			foreach($item as $ql => $obj) {
				$tmp[] = '['.$this->text->make_item($obj->lowid, $obj->highid, $ql, "QL$ql").' '.$this->priceToString($obj->price).']';
			}
			$out[] = sprintf("<tab>%s %s<br><tab>%s", $this->text->make_image($obj->icon), $obj->name, implode(' ', $tmp));
		}
		$out[] = $this->formatContacts($shop);
		$out = implode('<br><br><pagebreak>', $out);
		return $this->text->make_blob($this->getTitle($shop).' - '.$categories[$category], $out);
	}
	
	/**
	 * Generates the contact chunk for messages.
	 *
	 * @param array $shop - the shop array structur
	 * @return string - the formated string chunk
	 */
	public function formatContacts($shop) {
		$contacts = Array($shop->owner => ($this->buddylistManager->is_online($shop->owner) === 1));
		foreach($shop->contacts as $contact) {
			if($this->buddylistManager->is_online($contact->character) === 1) {
				$contacts[$contact->character] = true;
			}
		}
		foreach($contacts as $name => $online) {
			$contacts[$name] = $this->text->make_userlink($name).($online ? '' : ' (offline)');
		}
		return '<center>'.implode('  ', $contacts).'</center>';
	}
	
	/**
	 * Format search results for messages.
	 *
	 * @param array $items - the items array
	 * @return string - the formated string
	 */
	public function formatItems($items) {
		if(($c = count($items)) == 0) {
			return 'No items found.';
		}
		$categories = $this->getCategories();
		$cats = $categories;
		foreach($cats as $idx => $cat) {
			$cats[$idx] = Array();
		}
		foreach($items as $item) {
			$idx = $item->lowid.'/'.$item->highid;
			$cats[$item->category][$idx][] = $item;
		}
		$msg = Array();
		foreach($cats as $idx => $cat) {
			if(count($cat) == 0) {
				unset($cats[$idx]);
			}
			else {
				$tmp = $categories[$idx];
				foreach($cat as $itemSet) {
					$tmp .= '<br><tab>';
					$tmp2 = '';
					foreach($itemSet as $item) {
						$tmp2 .= '<br><tab><tab>'.$this->text->make_item($item->lowid, $item->highid, $item->ql, 'QL'.$item->ql).' '.$this->priceToString($item->price).' '.$this->text->make_chatcmd('contact', '/tell <myname> gms item '.$item->id);
					}
					$tmp .= $item->name.$tmp2.'<pagebreak>';
				}
				$msg[] = $tmp;
			}
		}
		return $this->text->make_blob("$c result(s)", implode('<br><br><pagebreak>', $msg));
	}
	
	/**
	 * Generates the shop blob title.
	 *
	 * @param array $shop - the shop array structur
	 * @return string - the shop blob title
	 */
	public function getTitle($shop) {
		if($this->util->endsWith($shop->owner, 's')) {
			return $shop->owner."' shop";
		}
		else {
			return $shop->owner.'s shop';
		}
	}
	
	/**
	 * Parses a price string to its integer value.
	 *
	 * @param string $price - the price string
	 * @retrun int - returns the integer value of the price, 0 if it is an offer, -1 if the price string is invalid.
	 */
	public function parsePrice($price) {
		$price = strtolower($price);
		if($price == 'offer') {
			return 0;
		}
		elseif(preg_match("~^\\d+$~",$price)) {
			$price = intval($price);
		}
		if(preg_match("~^(\\d*\\.?\\d+)(b|m|k)$~",$price,$match)) {
			$price = floatval('0'.$match[1]);
			switch($match[2]) {
				case 'b':
						$price *= 1000000000.0;
					break;
				case 'm':
						$price *= 1000000.0;
					break;
				case 'k':
						$price *= 1000.0;
					break;
			}
			$price = ceil($price);
		}
		else {
			return -1;
		}
		return $price;
	}
	
	/**
	 * Converts a price to its string.
	 *
	 * @param int $price - the price
	 * return string the string of the price
	 */
	public function priceToString($price) {
		if($price == 0) {
			return 'offer';
		}
		elseif($price < 1000) {
			return $price;
		}
		elseif($price < 1000000) {
			return ($price/1000.0).'k';
		}
		elseif($price < 1000000000) {
			return ($price/1000000.0).'m';
		}
		else {
			return ($price/1000000000.0).'b';
		}
	}
}
