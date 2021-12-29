<?php
namespace Sergey_Dertan\SClearLagg\SClearLaggMainFolder;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use Sergey_Dertan\SClearLagg\Command\SClearLaggCommandExecutor;
use Sergey_Dertan\SClearLagg\Entity\EntityManager;
use pocketmine\utils\TextFormat as F;
use Sergey_Dertan\SClearLagg\Task\TaskCreator;

/**
 * Class SClearLaggMain
 * @package Sergey_Dertan\SClearLagg\SClearLaggMainFolder
 */
class SClearLaggMain extends PluginBase
{
    /**
     * @var SClearLaggMain
     */
    private static $instance;
    public $config;
    /**
     * @var \Sergey_Dertan\SClearLagg\Entity\EntityManager
     */
    private $entityManager;

    function __construct()
    {
        self::$instance = $this;
        $this->entityManager = new EntityManager($this);
    }

    /**
     * @return SClearLaggMain
     */
    static function getInstance()
    {
        return self::$instance;
    }

    /**
     * @return EntityManager
     */
    function getEntityManager()
    {
        return $this->entityManager;
    }

    function onEnable()
    {
        @mkdir($this->getDataFolder());
        $this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML, array(
            "Clear-msg" => "Se elimino @count de lagg",
            "PreClear-msg" => "En un minuto de eliminara el lagg",
            "Clear-time" => 180
        ));
        new TaskCreator();
    }

    /**
     * @param CommandSender $s
     * @param Command $cmd
     * @param string $label
     * @param array $args
     * @return bool|SClearLaggCommandExecutor
     */
    function onCommand(CommandSender $s, Command $cmd, $label, array $args)
    {
        return new SClearLaggCommandExecutor($s, $cmd, $args);
    }

    function onDisable()
    {
        $this->config->save();
        $this->getLogger()->info(F::RED . "ClearLagg" . $this->getDescription()->getVersion() . "ClearLagg");
    }
}