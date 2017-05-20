<?php

/*
 *   ____  _            _      _       _     _
 *  |  _ \| |          | |    (_)     | |   | |
 *  | |_) | |_   _  ___| |     _  __ _| |__ | |_
 *  |  _ <| | | | |/ _ \ |    | |/ _` | '_ \| __|
 *  | |_) | | |_| |  __/ |____| | (_| | | | | |_
 *  |____/|_|\__,_|\___|______|_|\__, |_| |_|\__|
 *                                __/ |
 *                               |___/
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author BlueLightJapan Team
 * 
 */
 
namespace pocketmine\tile;

use pocketmine\inventory\BeaconInventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\level\Level;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;

class Beacon extends Spawnable implements Nameable, InventoryHolder {

	private $inventory;
	protected $currentTick = 0;
	const POWER_LEVEL_MAX = 4;

	public function __construct(Level $level, CompoundTag $nbt) {
		if (!isset($nbt->primary)) {
			$nbt->primary = new IntTag("primary", 0);
		}
		if (!isset($nbt->secondary)) {
			$nbt->secondary = new IntTag("secondary", 0);
		}
		$this->inventory = new BeaconInventory($this);
		parent::__construct($level, $nbt);
		$this->scheduleUpdate();
	}
	
	public function saveNBT() {
		parent::saveNBT();
	}
	
	public function getSpawnCompound() {
		$c = new CompoundTag("", [
			new StringTag("id", Tile::BEACON),
			new ByteTag("isMovable", (bool)true),
			new IntTag("x", (int)$this->x),
			new IntTag("y", (int)$this->y),
			new IntTag("z", (int)$this->z),
			new IntTag("primary", $this->namedtag["primary"]),
			new IntTag("secondary", $this->namedtag["secondary"])
		]);
		if ($this->hasName()) {
			$c->CustomName = $this->namedtag->CustomName;
		}
		return $c;
	}
	
	public function getName() : string{
		return $this->hasName() ? $this->namedtag->CustomName->getValue() : "Beacon";
	}
	
	public function hasName() {
		return isset($this->namedtag->CustomName);
	}
	
	public function setName($str) {
		if ($str === "") {
			unset($this->namedtag->CustomName);
			return;
		}
		$this->namedtag->CustomName = new StringTag("CustomName", $str);
	}
	
	public function getInventory() {
		return $this->inventory;
	}

	public function updateCompoundTag(CompoundTag $nbt, Player $player) : bool{
		if($nbt["id"] !== Tile::BEACON){
			return false;
		}
		$this->namedtag->primary = new IntTag("primary", $nbt["primary"]);
		$this->namedtag->secondary = new IntTag("secondary", $nbt["secondary"]);
		return true;
	}

	public function onUpdate(){
		if($this->closed === true){
			return false;
		}
		if($this->currentTick++ % 100 != 0) {
			return true;
		}

		$level = $this->calculatePowerLevel();

		$this->timings->startTiming();

		$ret = false;

		$id = 0;

		if($level > 0){
			if(isset($this->namedtag->secondary) && $this->namedtag["primary"] != 0){
				$id = $this->namedtag["primary"];
			}else if(isset($this->namedtag->secondary) && $this->namedtag["secondary"] != 0){
				$id = $this->namedtag["secondary"];
			}
			if($id != 0){
				$range = ($level + 1) * 10;
				$effect = Effect::getEffect($id);
				$effect->setDuration(10 * 20);
				$effect->setAmplifier(0);
				foreach($this->level->getPlayers() as $player){
					if($this->distance($player) <= $range){
						$player->addEffect($effect);
					}
				}
			}
		}

		$this->lastUpdate = microtime(true);

		$this->timings->stopTiming();

		return true;
	}

	protected function calculatePowerLevel(){
		$tileX = $this->getFloorX();
		$tileY = $this->getFloorY();
		$tileZ = $this->getFloorZ();
		for($powerLevel = 1; $powerLevel <= self::POWER_LEVEL_MAX; $powerLevel++) {
			$queryY = $tileY - $powerLevel;
			for ($queryX = $tileX - $powerLevel; $queryX <= $tileX + $powerLevel; $queryX++) {
				for ($queryZ = $tileZ - $powerLevel; $queryZ <= $tileZ + $powerLevel; $queryZ++) {
					$testBlockId = $this->level->getBlockIdAt($queryX, $queryY, $queryZ);
						if (
							$testBlockId != Block::IRON_BLOCK &&
							$testBlockId != Block::GOLD_BLOCK &&
							$testBlockId != Block::EMERALD_BLOCK &&
							$testBlockId != Block::DIAMOND_BLOCK
						) {
							return $powerLevel - 1;
						}
				}
			}
		}
		return self::POWER_LEVEL_MAX;
	}
}
