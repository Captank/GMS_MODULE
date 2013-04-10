<?php

/**
 * Author:
 *  - Captank (RK2)
 *
 * @Instance
 *
 */

class GMSCoreKernel {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;
	
	/** @Inject */
	public $db;
	
	/** @Inject */
	public $text;
	
	/** @Inject */
	public $util;
	
	/** @Inject */
	public $buddylistManager;
	
	/**
	 * @Setup
	 */
	public function setup() {		
		$this->db->loadSQLFile($this->moduleName, "gms");
		$this->db->loadSQLFile($this->moduleName, "gms_categories");
		$this->db->loadSQLFile($this->moduleName, "gms_item_data");
	}
	
	/**
	 * This function adds an item to a shop, if the item already exists,
	 * it updates the price.
	 *
	 * @param mixed $shop - the shop object
	 * @param int $lowid - lowid of the item
	 * @param int $highid - highid of the item
	 * @param int $ql - ql of the item
	 * @param int $price - the price for the item
	 * @return int - 0 if already in shop, 1 if only price changed, 2 if added, -1 if invalid item
	 */
	public function addItem($shop, $lowid, $highid, $ql, $price) {
		$sql = <<<EOD
SELECT
    `id`, `price`
FROM
    `gms_items`
WHERE
    `shopid` = ? AND `lowid` = ? AND `highid` = ? AND `ql` = ?
LIMIT 1;
EOD;
		$item = $this->db->query($sql, $shop->id, $lowid, $highid, $ql);
		if(count($item) == 1) {
			if($item[0]->price == $price || $price == 0) {
				return 0;
			}
			else{
				$sql = <<<EOD
UPDATE
	`gms_items`
SET
	`price` = ?
WHERE
	`id` = ?
EOD;
				$this->db->exec($sql, $price, $item->id);
				return 1;
			}
		}
		elseif(false /*check for invalid item*/){
			return -1;
		}
		else {
			$sql = <<<EOD
INSERT INTO
	`gms_items`
	(`shopid`, `lowid`, `highid`, `ql`, `price`)
VALUES
	(?, ?, ?, ?, ?);
EOD;
			$this->db->exec($sql, $shop->id, $lowid, $highid, $ql, $price);
			return 2;
		}
	}
	
	/**
	 * This functions adds an contact to a shop.
	 *
	 * @param mixed $shop - the shop object
	 * @param string $character - the name of the character
	 */
	public function addContact($shop, $character) {
		$sql = <<<EOD
INSERT INTO
    `gms_contacts`
    (`shopid`,`character`)
VALUES
    (?, ?);
EOD;
		$this->db->exec($sql, $shop->id, $character);
	}
	
	/**
	 * Format shop category for messages.
	 *
	 * @params array $shop - the shop array structur
	 * @params int $category - the category id
	 * @params bool $owner - defines if its the owner of the shop, default false
	 * @return string - the formated message blob
	 */
	public function formatCategory($shop, $category, $owner = false) {
		$categories = self::getCategories();
		if(!isset($categories[$category])) {
			return "Error! Invalid id '$category'";
		}
		$items = Array();
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
		if($owner) {
			foreach($items as $item) {
				$tmp = Array();
				foreach($item as $ql => $obj) {
					$tmp[] = '['.$this->text->make_item($obj->lowid, $obj->highid, $ql, "QL$ql").' '.self::priceToString($obj->price).' - '.$this->text->make_chatcmd('remove',sprintf('/tell <myname> cgms rem %d', $obj->id)).']';
				}
				$out[] = sprintf("<tab>%s %s<br><tab>%s", $this->text->make_image($obj->icon), $obj->name, implode(' ', $tmp));
			}
		}
		else {
			foreach($items as $item) {
				$tmp = Array();
				foreach($item as $ql => $obj) {
					$tmp[] = '['.$this->text->make_item($obj->lowid, $obj->highid, $ql, "QL$ql").' '.self::priceToString($obj->price).']';
				}
				$out[] = sprintf("<tab>%s %s<br><tab>%s", $this->text->make_image($obj->icon), $obj->name, implode(' ', $tmp));
			}
		}
		
		if(count($out) == 0) {
			$out[] = '<tab>This category is empty at the moment.';
		}
		
		if($owner) {
			$out[] = '<center>'.$this->text->make_chatcmd('contact list', '/tell <myname> cgms contacts').'</center>';
		}
		else {
			$out[] = self::formatContacts($shop);
		}
		$out = implode('<br><br><pagebreak>', $out);
		return $this->text->make_blob(self::getTitle($shop).' - '.$categories[$category], $out);
	}
	
	/**
	 * Generates the contact chunk for messages or the list of contacts depending
	 * on $asList.
	 *
	 * @param array $shop - the shop array structur
	 * @param boolean $asList - defines if shows
	 * @return string - the formated string (chunk)
	 */
	public function formatContacts($shop, $asList = false) {
		if($asList) {
			$contacts = Array();
			foreach($shop->contacts as $contact) {
				$contacts[] = '<tab>'.$contact->character;
			}
			if(count($contacts) == 0) {
				return 'No contacts set besides '.$shop->owner.'.';
			}
			else {
				return $this->text->make_blob(self::getTitle($shop).' - Contacts', implode('<br>', $contacts));
			}
		}
		else {
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
	}
	
	/**
	 * Format an item entry for messages.
	 *
	 * @param array $shop - the shop array structur
	 * @return string - the formated message blob
	 */
	public function formatItemEntry($shop) {
		$categories = self::getCategories();
		$msg = 	$categories[$shop->itemEntry->category].'<br><br><tab>'.
				$this->text->make_item($shop->itemEntry->lowid, $shop->itemEntry->highid, $shop->itemEntry->ql, $shop->itemEntry->ql.' '.$shop->itemEntry->name).
				'<br><tab>Price: '.$this->priceToString($shop->itemEntry->price).'<br>'.
				$this->formatContacts($shop);
		return $this->text->make_blob('Item details', $msg);
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
		$categories = self::getCategories();
		$cats = $categories;
		foreach($cats as $idx => $cat) {
			$cats[$idx] = Array();
		}
		foreach($items as $item) {
			$idx = $item->lowid.'/'.$item->highid;
			if($item->category !== null) {
				$cats[$item->category][$idx][] = $item;
			}
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
						$tmp2 .= '<br><tab><tab>'.$this->text->make_item($item->lowid, $item->highid, $item->ql, 'QL'.$item->ql).' '.self::priceToString($item->price).' '.$this->text->make_chatcmd('contact', '/tell <myname> cgms item '.$item->id);
					}
					$tmp .= $item->name.$tmp2.'<pagebreak>';
				}
				$msg[] = $tmp;
			}
		}
		return $this->text->make_blob("$c result(s)", implode('<br><br><pagebreak>', $msg));
	}
	
	/**
	 * Format shop for messages.
	 *
	 * @params array $shop - the shop array structur
	 * @return string - the formated message blob
	 */
	public function formatShop($shop) {
		$categories = self::getCategories();
		
		$cats = Array();
		foreach($shop->items as $item) {
			if($item->category !== null) {
				if(isset($cats[$item->category])) {
					$cats[$item->category]++;
				}
				else {
					$cats[$item->category] = 1;
				}
			}
		}
		
		if(count($cats) == 0) {
			$cats[] = '<tab>This shop is empty at the moment.';
		}
		else {
			foreach($cats as $cid => &$cat) {
				$cat = sprintf('<tab>%s (%d %s)', $this->text->make_chatcmd($categories[$cid], '/tell <myname> cgms show '.$shop->id.' '.$cid), $cat, ($cat > 1 ? 'items' : 'item'));
			}
		}
		$cats[] = self::formatContacts($shop);
		return $this->text->make_blob(self::getTitle($shop), implode('<br><br>',$cats));
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
	 * Get all relevant shop data for an item entry.
	 *
	 * @param int $id - the id of the item entry
	 * @return array - the shop array structure, null if invalid id
	 */
	public function getItemEntry($id) {
	$sql = <<<EOD
SELECT
	`gms_items`.`id`,
	`gms_items`.`shopid`,
	`gms_items`.`lowid`,
	`gms_items`.`highid`,
	`gms_items`.`ql`,
	`gms_item_data`.`name`,
	`gms_item_data`.`icon`,
	`gms_items`.`price`,
	`gms_item_data`.`category`
FROM
	`gms_items`
		LEFT JOIN
	`gms_item_data` ON `gms_item_data`.`lowid` = `gms_items`.`lowid` AND `gms_item_data`.`highid` = `gms_items`.`highid`
WHERE
	`gms_items`.`id` = ?
EOD;
		$item = $this->db->query($sql, $id);
		if(count($item) != 1) {
			return null;
		}
		
		$item = $item[0];
		$result = self::getShop($item->shopid, true, false);
		$result->itemEntry = $item;
		return $result;
		
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
SELECT
	`gms_shops`.`id`,
	`gms_shops`.`owner`
FROM
	`gms_shops`
WHERE
	`gms_shops`.`owner` = ?
LIMIT 1
EOD;
			$identifier = ucfirst(strtolower($identifier));
			$shop = $this->db->query($sql, $identifier);
			if(count($shop) == 0) {
				$sql = <<<EOD
SELECT
	`gms_shops`.`owner`,
	`gms_shops`.`id`
FROM
	`gms_contacts`
		LEFT JOIN
	`gms_shops`
		ON `gms_contacts`.`shopid` = `gms_shops`.`id`
WHERE
	`gms_contacts`.`character` = ?
LIMIT 1
EOD;
				$shop = $this->db->query($sql, $identifier);
				if(count($shop) == 0) {
					return null;
				}
			}
		}
		$shop = $shop[0];

		if($contacts) {
			$shop->contacts = self::getShopContacts($shop->id);
		}
		else {
			$shop->contacts = null;
		}
		if($items) {
			$shop->items = self::getShopItems($shop->id);
		}
		else {
			$shop->items = null;
		}
		return $shop;
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
	 * Get items data for a shop
	 *
	 * @param int $shopid - the shop id
	 * @param mixed $category - int for the category id, false for all categories
	 * @return array - array of DBRows for the item data
	 */
	public function getShopItems($shopid, $category = false) {
		$data = Array($shopid);
		
		if($category !== false) {
			$sql = " AND `gms_item_data`.`category` = ?";
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
	`gms_item_data`.`name`,
	`gms_item_data`.`icon`,
	`gms_items`.`price`,
	`gms_item_data`.`category`
FROM
	`gms_items`
		LEFT JOIN
	`gms_item_data` ON `gms_item_data`.`lowid` = `gms_items`.`lowid` AND `gms_item_data`.`highid` = `gms_items`.`highid`
WHERE
	`gms_items`.`shopid` = ?$sql
ORDER BY
	`gms_item_data`.`category` ASC, `gms_item_data`.`name` ASC, `gms_items`.`ql` ASC, `gms_items`.`price` ASC
EOD;
		return $this->db->query($sql, $data);
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
				$sqlPattern[] = "`gms_item_data`.`name` LIKE ?";
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
	`gms_item_data`.`icon`,
	`gms_item_data`.`name`,
	`gms_item_data`.`category`
FROM
	`gms_items`
		LEFT JOIN
	`gms_item_data` ON `gms_items`.`lowid` = `gms_item_data`.`lowid` AND `gms_items`.`highid` = `gms_item_data`.`highid`
WHERE
	$sql
ORDER BY
	`gms_item_data`.`category` ASC, `gms_item_data`.`name` ASC, `gms_items`.`ql` ASC, `gms_items`.`price` ASC
LIMIT 40
EOD;
		return $this->db->query($sql, $data);
	}
	
	/**
	 * Parses a price string to its integer value.
	 *
	 * @param string $price - the price string
	 * @retrun int - returns the integer value of the price, 0 if it is an offer, -1 if the price string is invalid.
	 */
	public function parsePrice($price) {
		var_dump($price);
		$price = strtolower($price);
		if($price == 'offer') {
			return 0;
		}
		elseif(preg_match("~^\\d+$~",$price)) {
			$price = intval($price);
		}
		elseif(preg_match("~^(\\d*\\.?\\d+)(b|m|k)$~",$price,$match)) {
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
		
	/**
	 * This function is to register a new shop.
	 *
	 * @param string $owner - name of the owner
	 * @return mixed - true if okay, shop object, if already registered.
	 */
	public function registerShop($owner) {
		if(($shop = self::getShop($owner, false, false)) !== NULL) {
			return $shop;
		}
		else {
			$sql = <<<EOD
INSERT INTO
	`gms_shops`
    (`owner`)
VALUES
    (?)
EOD;
			$this->db->exec($sql, $owner);
			return true;
		}
	}
		
	/**
	 * This functions removes an contact from a shop.
	 *
	 * @param mixed $shop - the shop object
	 * @param string $character - the name of the character
	 */
	public function removeContact($character) {
		$sql = <<<EOD
DELETE FROM
	`gms_contacts`
WHERE
	`character` = ?
LIMIT 1;
EOD;
		$this->db->exec($sql, $character);
	}
	
	/**
	 * This functions removes an item from a shop.
	 *
	 * @param mixed $shop - the shop object
	 * @param int $item - id of the item
	 */
	public function removeItem($item) {
		$sql = <<<EOD
DELETE FROM
	`gms_items`
WHERE
	`id` = ?
LIMIT 1;
EOD;
		$this->db->exec($sql, $item);
	}
}
