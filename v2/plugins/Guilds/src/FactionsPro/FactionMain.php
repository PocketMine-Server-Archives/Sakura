<?php

namespace FactionsPro;

/*
 */

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use onebone\economyapi\EconomyAPI;

class FactionMain extends PluginBase implements Listener {

    public $db;
    public $prefs;
    public $war_req = [];
    public $wars = [];
    public $war_players = [];
    //public $chatcensor;

    public function onEnable() {

        @mkdir($this->getDataFolder());

        if (!file_exists($this->getDataFolder() . "DisableGuildsName.txt")) {
            $file = fopen($this->getDataFolder() . "DisableGuildsName.txt", "w");
            $txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
            fwrite($file, $txt);
        }


        $this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);


        $this->fCommand = new FactionCommands($this);

        $this->prefs = new Config($this->getDataFolder() . "GuildsOptions.yml", CONFIG::YAML, array(
            "MaxFactionNameLength" => 15,
            "MaxPlayersPerFaction" => 30,
            "OnlyLeadersAndOfficersCanInvite" => true,
            "###Plots###",
		    "OfficersCanClaim" => false,
	    	"PlotSize" => 30,
            "PlayersNeededInFactionToClaimAPlot" => 5,
            "ClaimWorlds" => [],
            "###Guilds Power###",
            "PowerNeededToClaimAPlot" => 1000,
            "PowerNeededToSetOrUpdateAHome" => 250,
            "PowerGainedPerPlayerInFaction" => 50,
            "PowerGainedPerKillingAnEnemy" => 10,
    		"PowerReducedPerDeathByAnEnemy" => 10,
            "PowerGainedPerAlly" => 100,
            "AllyLimitPerFaction" => 5,
            "TheDefaultPowerEveryFactionStartsWith" => 0,
            "###Economys###",
            "CreateCost" => 3000,
		    "ClaimCost" => 100000,
	    	"OverClaimCost" => 25000,
	    	"AllyCost" => 5000,
	    	"AllyPrice" => 5000,
	    	"SetHomeCost" => 150,
          "###GuildsMoneys###",
          "GuildsMoneyGainPerKill" => 10,
          "GuildsMoneyLostPerDeath" => 10,
        ));
        $this->db = new \SQLite3($this->getDataFolder() . "GuildsDatabase.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliance (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, requestedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS strength(faction TEXT PRIMARY KEY, power INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS allies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS enemies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliescountlimit(faction TEXT PRIMARY KEY, count INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS effects(faction TEXT PRIMARY KEY, effect TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS moneys(faction TEXT PRIMARY KEY, moneys INT);");
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        $this->fCommand->onCommand($sender, $command, $label, $args);
    }

    public function addEffectTo($faction,$effect){
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO effects (faction, effect) VALUES (:faction, :effect);");  
        $stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":effect", $effect);
		$result = $stmt->execute();
    }
    public function getEffectOf($faction){
        $result = $this->db->query("SELECT * FROM effects WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if(empty($resultArr)){
            return "none";
        }
        return $resultArr['effect'];
    }
    public function setEnemies($faction1, $faction2) {
        $stmt = $this->db->prepare("INSERT INTO enemies (faction1, faction2) VALUES (:faction1, :faction2);");
        $stmt->bindValue(":faction1", $faction1);
        $stmt->bindValue(":faction2", $faction2);
        $result = $stmt->execute();
    }

    public function areEnemies($faction1, $faction2) {
        $result = $this->db->query("SELECT * FROM enemies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr) == false) {
            return true;
        }
    }

    public function isInFaction($player) {
        $result = $this->db->query("SELECT * FROM master WHERE player='$player';");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }

    public function getFaction($player) {
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["faction"];
    }

    public function setFactionPower($faction, $power) {
        if ($power < 0) {
            $power = 0;
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":power", $power);
        $result = $stmt->execute();
    }

    public function setAllies($faction1, $faction2) {
        $stmt = $this->db->prepare("INSERT INTO allies (faction1, faction2) VALUES (:faction1, :faction2);");
        $stmt->bindValue(":faction1", $faction1);
        $stmt->bindValue(":faction2", $faction2);
        $result = $stmt->execute();
    }

    public function areAllies($faction1, $faction2) {
        $result = $this->db->query("SELECT * FROM allies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr) == false) {
            return true;
        }
    }

    public function updateAllies($faction) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO alliescountlimit(faction, count) VALUES (:faction, :count);");
        $stmt->bindValue(":faction", $faction);
        $result = $this->db->query("SELECT * FROM allies WHERE faction1='$faction';");
        $i = 0;
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $i = $i + 1;
        }
        $stmt->bindValue(":count", (int) $i);
        $result = $stmt->execute();
    }

    public function getAlliesCount($faction) {

        $result = $this->db->query("SELECT * FROM alliescountlimit WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["count"];
    }

    public function getAlliesLimit() {
        return (int) $this->prefs->get("AllyLimitPerFaction");
    }

    public function deleteAllies($faction1, $faction2) {
        $stmt = $this->db->prepare("DELETE FROM allies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $result = $stmt->execute();
    }

    public function getFactionPower($faction) {
        $result = $this->db->query("SELECT * FROM strength WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["power"];
    }

    public function addFactionPower($faction, $power) {
        if ($this->getFactionPower($faction) + $power < 0) {
            $power = $this->getFactionPower($faction);
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":power", $this->getFactionPower($faction) + $power);
        $result = $stmt->execute();
    }

    public function subtractFactionPower($faction, $power) {
        if ($this->getFactionPower($faction) - $power < 0) {
            $power = $this->getFactionPower($faction);
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":power", $this->getFactionPower($faction) - $power);
        $result = $stmt->execute();
    }

    public function isLeader($player) {
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["rank"] == "Leader";
    }

    public function isOfficer($player) {
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["rank"] == "Officer";
    }

    public function isMember($player) {
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["rank"] == "Member";
    }

    public function getPlayersInFactionByRank($s, $faction, $rank) {

        if ($rank != "Leader") {
            $rankname = $rank . 's';
        } else {
            $rankname = $rank;
        }
        $team = "";
        $result = $this->db->query("SELECT * FROM master WHERE faction='$faction' AND rank='$rank';");
        $row = array();
        $i = 0;

        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $row[$i]['player'] = $resultArr['player'];
            if ($this->getServer()->getPlayerExact($row[$i]['player']) instanceof Player) {
                $team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::GREEN . "[ON]" . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
            } else {
                $team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::RED . "[OFF]" . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
            }
            $i = $i + 1;
        }

        $s->sendMessage($this->formatMessage("~ *<$rankname> of |$faction|* ~", true));
        $s->sendMessage($team);
    }

    public function getAllAllies($s, $faction) {

        $team = "";
        $result = $this->db->query("SELECT * FROM allies WHERE faction1='$faction';");
        $row = array();
        $i = 0;
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $row[$i]['faction2'] = $resultArr['faction2'];
            $team .= TextFormat::ITALIC . TextFormat::RED . $row[$i]['faction2'] . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
            $i = $i + 1;
        }

        $s->sendMessage($this->formatMessage("§l§b»§r§e Allies of $faction §l§b«", true));
        $s->sendMessage($team);
    }

    public function sendListOfTop10FactionsTo($s) {
        $tf = "";
        $result = $this->db->query("SELECT faction FROM strength ORDER BY power DESC LIMIT 10;");
        $row = array();
        $i = 0;
        $s->sendMessage($this->formatMessage("§c⊹⊱§eXếp Hạng§b Bang Hội§e Của Máy Chủ§c⊰⊹", true));
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $j = $i + 1;
            $cf = $resultArr['faction'];
            $pf = $this->getFactionPower($cf);
            $df = $this->getNumberOfPlayers($cf);
$s->sendMessage("§f|§a $j §f| §aBang Hội:§b $cf §f|§b $pf §aĐiểm §f|§b $df §aNgười Tham Gia §f|");
            $i = $i + 1;
        }
    }

    public function getPlayerFaction($player) {
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["faction"];
    }

    public function getLeader($faction) {
        $leader = $this->db->query("SELECT * FROM master WHERE faction='$faction' AND rank='Leader';");
        $leaderArray = $leader->fetchArray(SQLITE3_ASSOC);
        return $leaderArray['player'];
    }

    public function factionExists($faction) {
        $result = $this->db->query("SELECT * FROM master WHERE faction='$faction';");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }

    public function sameFaction($player1, $player2) {
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player1';");
        $player1Faction = $faction->fetchArray(SQLITE3_ASSOC);
        $faction = $this->db->query("SELECT * FROM master WHERE player='$player2';");
        $player2Faction = $faction->fetchArray(SQLITE3_ASSOC);
        return $player1Faction["faction"] == $player2Faction["faction"];
    }

    public function getNumberOfPlayers($faction) {
        $query = $this->db->query("SELECT COUNT(*) as count FROM master WHERE faction='$faction';");
        $number = $query->fetchArray();
        return $number['count'];
    }

    public function isFactionFull($faction) {
        return $this->getNumberOfPlayers($faction) >= $this->prefs->get("MaxPlayersPerFaction");
    }

    public function isNameBanned($name) {
        $bannedNAmes = file_get_contents($this->getDataFolder() . "DisableGuildsName.txt");
        return (strpos(strtolower($bannedNAmes), strtolower($name)));
    }

    public function newPlot($faction, $x1, $z1, $x2, $z2) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO plots (faction, x1, z1, x2, z2) VALUES (:faction, :x1, :z1, :x2, :z2);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":x1", $x1);
        $stmt->bindValue(":z1", $z1);
        $stmt->bindValue(":x2", $x2);
        $stmt->bindValue(":z2", $z2);
        $result = $stmt->execute();
    }

    public function drawPlot($sender, $faction, $x, $y, $z, $level, $size) {
        $arm = ($size - 1) / 2;
        $block = new Snow();
        if ($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm)) {
            $claimedBy = $this->factionFromPoint($x, $z);
            $power_claimedBy = $this->getFactionPower($claimedBy);
            $power_sender = $this->getFactionPower($faction);

            if ($this->prefs->get("EnableOverClaim")) {
                if ($power_sender < $power_claimedBy) {
                    $sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy with $power_claimedBy STR. Your faction has $power_sender power. You don't have enough power to overclaim this plot."));
                } else {
                    $sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy with $power_claimedBy STR. Your faction has $power_sender power. Type /f overclaim to overclaim this plot if you want."));
                }
                return false;
            } else {
                $sender->sendMessage($this->formatMessage("Overclaiming is disabled."));
                return false;
            }
        }
        $level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
        $level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
        $this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
        return true;
    }

    public function isInPlot($player) {
        $x = $player->getFloorX();
        $z = $player->getFloorZ();
        $result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }

    public function factionFromPoint($x, $z) {
        $result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return $array["faction"];
    }

    public function inOwnPlot($player) {
        $playerName = $player->getName();
        $x = $player->getFloorX();
        $z = $player->getFloorZ();
        return $this->getPlayerFaction($playerName) == $this->factionFromPoint($x, $z);
    }

    public function pointIsInPlot($x, $z) {
        $result = $this->db->query("SELECT * FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return !empty($array);
    }

    public function cornerIsInPlot($x1, $z1, $x2, $z2) {
        return($this->pointIsInPlot($x1, $z1) || $this->pointIsInPlot($x1, $z2) || $this->pointIsInPlot($x2, $z1) || $this->pointIsInPlot($x2, $z2));
    }

    public function formatMessage($string, $confirm = false) {
        if ($confirm) {
            return TextFormat::GREEN . "$string";
        } else {
            return TextFormat::YELLOW . "$string";
        }
    }

    public function motdWaiting($player) {
        $stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$player';");
        $array = $stmt->fetchArray(SQLITE3_ASSOC);
        return !empty($array);
    }

    public function getMOTDTime($player) {
        $stmt = $this->db->query("SELECT * FROM motdrcv WHERE player='$player';");
        $array = $stmt->fetchArray(SQLITE3_ASSOC);
        return $array['timestamp'];
    }

    public function setMOTD($faction, $player, $msg) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":message", $msg);
        $result = $stmt->execute();

        $this->db->query("DELETE FROM motdrcv WHERE player='$player';");
    }

    public function updateTag($player) {
        $p = $this->getServer()->getPlayer($player);
        $f = $this->getPlayerFaction($player);
        $n = $this->getNumberOfPlayers($f);
        if (!$this->isInFaction($player)) {
            $p->setNameTag(TextFormat::ITALIC . TextFormat::YELLOW . "<$player>");
        } else {
            $p->setNameTag(TextFormat::ITALIC . TextFormat::GOLD . "<$f> " .
                    TextFormat::ITALIC . TextFormat::YELLOW . "<$player>");
        }
    }
///TEST::FACTIONMONEY///
    public function getFactionMoney($faction) {
        $result = $this->db->query("SELECT * FROM moneys WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["moneys"];
    }

    public function addFactionMoney($faction, $money) {
        if ($this->getFactionMoney($faction) + $money < 0) {
            $money = $this->getFactionMoney($faction);
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO moneys (faction, moneys) VALUES (:faction, :moneys);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":moneys", $this->getFactionMoney($faction) + $money);
        $result = $stmt->execute();
    }

    public function subtractFactionMoney($faction, $money) {
        if ($this->getFactionMoney($faction) - $money < 0) {
            $money = $this->getFactionMoney($faction);
        }
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO moneys (faction, moneys) VALUES (:faction, :moneys);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":moneys", $this->getFactionMoney($faction) - $money);
        $result = $stmt->execute();
    }

    public function onDisable() {
        $this->db->close();
    }

}
