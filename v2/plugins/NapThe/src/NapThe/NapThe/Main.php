<?php

namespace NapThe\NapThe;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{
	
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "napthe":
			   $sender->sendMessage("§e⊹⊱§aLiên Hệ Người Chơi:§b Vohuynhnamviet§a Hoặc §bGhast_Noob§a Để Nạp Thẻ. Xin Cảm Ơn!§e⊰⊹");
		return true;
			default:
			   return false;
		}
	}
}