<?php

/*
 *
 *      ______           __  _                __  ___           __
 *     / ____/___ ______/ /_(_)___  ____     /  |/  /___ ______/ /____  _____
 *    / /_  / __ `/ ___/ __/ / __ \/ __ \   / /|_/ / __ `/ ___/ __/ _ \/ ___/
 *   / __/ / /_/ / /__/ /_/ / /_/ / / / /  / /  / / /_/ (__  ) /_/  __/ /  
 *  /_/    \__,_/\___/\__/_/\____/_/ /_/  /_/  /_/\__,_/____/\__/\___/_/ 
 *
 * FactionMaster - A Faction plugin for PocketMine-MP
 * This file is part of FactionMaster
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @author ShockedPlot7560 
 * @link https://github.com/ShockedPlot7560
 * 
 *
*/

namespace ShockedPlot7560\FactionMasterBank;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Button\Collection\CollectionFactory;
use ShockedPlot7560\FactionMaster\Button\Collection\MainFacCollection;
use ShockedPlot7560\FactionMaster\Extension\Extension;
use ShockedPlot7560\FactionMaster\libs\JackMD\ConfigUpdater\ConfigUpdater;
use ShockedPlot7560\FactionMaster\libs\JackMD\UpdateNotifier\UpdateNotifier;
use ShockedPlot7560\FactionMaster\Main;
use ShockedPlot7560\FactionMaster\Manager\ExtensionManager as ManagerExtensionManager;
use ShockedPlot7560\FactionMaster\Manager\LeaderboardManager;
use ShockedPlot7560\FactionMaster\Manager\MigrationManager;
use ShockedPlot7560\FactionMaster\Manager\PermissionManager as ManagerPermissionManager;
use ShockedPlot7560\FactionMaster\Permission\Permission;
use ShockedPlot7560\FactionMaster\Reward\RewardFactory;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\Button\Bank;
use ShockedPlot7560\FactionMasterBank\Button\Collection\HistoryBank;
use ShockedPlot7560\FactionMasterBank\Button\Collection\MainBank as CollectionMainBank;
use ShockedPlot7560\FactionMasterBank\Database\Table\BankHistoryTable;
use ShockedPlot7560\FactionMasterBank\Database\Table\MoneyTable;
use ShockedPlot7560\FactionMasterBank\Leaderboard\FactionMoneyLeaderboard;
use ShockedPlot7560\FactionMasterBank\Listener\EventListener;
use ShockedPlot7560\FactionMasterBank\Listener\ScoreHudListener;
use ShockedPlot7560\FactionMasterBank\Reward\Money;
use ShockedPlot7560\FactionMasterBank\Route\BankDeposit;
use ShockedPlot7560\FactionMasterBank\Route\BankHistory;
use ShockedPlot7560\FactionMasterBank\Route\BankWithdraw;
use ShockedPlot7560\FactionMasterBank\Route\MainBank;
use ShockedPlot7560\FactionMasterBank\Task\SyncServerTask;

class FactionMasterBank extends PluginBase implements Extension {

    /** @var Config */
    private $config;
    /** @var Config[] */
    private $langConfig;
    /** @var FactionMasterBank */
    private static $instance;

    public function onLoad(): void {

        self::$instance = $this;

        if (!$this->getServer()->getPluginManager()->getPlugin("EconomyAPI") instanceof Plugin) {
            $this->getLogger()->warning($this->getExtensionName() . " required EconomyAPI to use, please install them and restart your server");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->initConfigLang();
        (new BankHistoryTable(MainAPI::$PDO))->init();
        (new MoneyTable(MainAPI::$PDO))->init();
        BankAPI::init();

        ManagerExtensionManager::registerExtension($this);
        MigrationManager::addConfigDbToCheck([
            "CONFIG_INST" => new Config($this->getDataFolder() . "config.yml", Config::YAML),
            "CONFIG_NAME" => "default-faction-money",
            "TABLE_NAME" => MoneyTable::TABLE_NAME,
            "COLUMN_NAME" => "amount",
            "TABLE_CLASS" => MoneyTable::class
        ]);
    }

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        if ($this->getServer()->getPluginManager()->getPlugin("ScoreHud") instanceof Plugin) {
            $this->getServer()->getPluginManager()->registerEvents(new ScoreHudListener($this), $this);
        }
        UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());
        $this->getScheduler()->scheduleRepeatingTask(new SyncServerTask($this), (int) Utils::getConfig("sync-time"));
        LeaderboardManager::registerLeaderboard(new FactionMoneyLeaderboard(Main::getInstance()));
    }

    public function execute(): void {
        $this->registerPermission();
        $this->registerCollection();
        $this->registerRoute();

        RewardFactory::registerReward(new Money());
    }

    public function getExtensionName(): string {
        return "FactionMaster-Bank";
    }

    /** @return Config[] */
    public function getlangConfig(): array {
        return $this->langConfig;
    }

    public function getConfigBank(): Config {
        return $this->config;
    }

    private function initConfigLang(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->saveResource('fr_FR.yml');
        $this->saveResource('en_EN.yml');
        $this->saveResource('tr_TR.yml');
        $this->saveResource('config.yml');
        $this->config = new Config($this->getDataFolder() . "config.yml");
        ConfigUpdater::checkUpdate($this, $this->config, "file-version", 1);
        ConfigUpdater::checkUpdate($this, new Config($this->getDataFolder() . "fr_FR.yml", Config::YAML), "file-version", 1);
        ConfigUpdater::checkUpdate($this, new Config($this->getDataFolder() . "en_EN.yml", Config::YAML), "file-version", 1);
        ConfigUpdater::checkUpdate($this, new Config($this->getDataFolder() . "tr_TR.yml", Config::YAML), "file-version", 1);
        $this->langConfig = [
            "FR" => new Config($this->getDataFolder() . "fr_FR.yml", Config::YAML),
            "EN" => new Config($this->getDataFolder() . "en_EN.yml", Config::YAML),
            "TR" => new Config($this->getDataFolder() . "tr_TR.yml", Config::YAML)
        ];
    }

    private function registerPermission(): void {
        if ($this->config->get("bank-deposit") == true) {
            ManagerPermissionManager::registerPermission(new Permission(
                "PERMISSION_BANK_DEPOSIT", 
                function(string $playerName) { return Utils::getText($playerName, "PERMISSION_BANK_DEPOSIT");}, 
                PermissionIdsBank::PERMISSION_BANK_DEPOSIT), true);
        }
        if ($this->config->get("bank-withdraw") == true) {
            ManagerPermissionManager::registerPermission(new Permission(
                "PERMISSION_BANK_WITHDRAW", 
                function(string $playerName) { return Utils::getText($playerName, "PERMISSION_BANK_WITHDRAW");}, 
                PermissionIdsBank::PERMISSION_BANK_WITHDRAW), true);
        }
        if ($this->config->get("bank-history") == true) {
            ManagerPermissionManager::registerPermission(new Permission(
                "PERMISSION_SEE_BANK_HISTORY", 
                function(string $playerName) { return Utils::getText($playerName, "PERMISSION_SEE_BANK_HISTORY");}, 
                PermissionIdsBank::PERMISSION_SEE_BANK_HISTORY), true);
        }
    }

    private function registerRoute(): void {
        $routes = [
            MainBank::class
        ];
        if ($this->config->get("bank-deposit") == true) {
            $routes[] = BankDeposit::class;
        }
        if ($this->config->get("bank-history") == true) {
            $routes[] = BankHistory::class;
        }
        if ($this->config->get("bank-withdraw") == true) {
            $routes[] = BankWithdraw::class;
        }
        foreach ($routes as $route) {
            RouterFactory::registerRoute(new $route());
        }
    }

    private function registerCollection(): void {
        $ButtonCollection = CollectionFactory::get(MainFacCollection::SLUG);
        $ButtonCollection->registerCallable("FactionMasterBank", function() use ($ButtonCollection) {
            $ButtonCollection->register(new Bank(), 0);
        });
        CollectionFactory::register(new CollectionMainBank());
        CollectionFactory::register(new HistoryBank());
    }

    public static function getInstance(): self {
        return self::$instance;
    }

}