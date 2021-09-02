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

use PDOException;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Button\Collection\CollectionFactory;
use ShockedPlot7560\FactionMaster\Button\Collection\MainCollectionFac;
use ShockedPlot7560\FactionMaster\Database\Database;
use ShockedPlot7560\FactionMaster\Database\Table\FactionTable;
use ShockedPlot7560\FactionMaster\Extension\Extension;
use ShockedPlot7560\FactionMaster\Main;
use ShockedPlot7560\FactionMaster\Permission\Permission;
use ShockedPlot7560\FactionMaster\Reward\RewardFactory;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\Button\Bank;
use ShockedPlot7560\FactionMasterBank\Button\Collection\HistoryBank;
use ShockedPlot7560\FactionMasterBank\Button\Collection\MainBank as CollectionMainBank;
use ShockedPlot7560\FactionMasterBank\Database\Table\BankHistoryTable;
use ShockedPlot7560\FactionMasterBank\Reward\Money;
use ShockedPlot7560\FactionMasterBank\Route\BankDeposit;
use ShockedPlot7560\FactionMasterBank\Route\BankHistory;
use ShockedPlot7560\FactionMasterBank\Route\MainBank;

class FactionMasterBank extends PluginBase implements Extension {

    /** @var Config */
    private $config;
    /** @var Config[] */
    private $LangConfig;

    public function onLoad() {

        if (!$this->getServer()->getPluginManager()->getPlugin("EconomyAPI") instanceof Plugin) {
            $this->getLogger()->warning($this->getExtensionName() . " required EconomyAPI to use, please install them and restart your server");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->initConfigLang();
        (new BankHistoryTable(MainAPI::$PDO))->init();
        try {
            $defaultMoney = (int) $this->config->get("default-faction-money");
            if (Utils::getConfig("PROVIDER") === Database::MYSQL_PROVIDER) {
                $query = MainAPI::$PDO->prepare("SHOW COLUMNS FROM ".FactionTable::TABLE_NAME." LIKE 'money'");
                $query->execute();    
                if ($query->fetch() == 0) {
                    $query = MainAPI::$PDO->prepare("ALTER TABLE ".FactionTable::TABLE_NAME." ADD `money` BIGINT NOT NULL DEFAULT '$defaultMoney' AFTER `date`;");
                    $query->execute(); 
                }  
            }else{
                $query = MainAPI::$PDO->prepare("PRAGMA table_info(" . FactionTable::TABLE_NAME . ")");
                $query->execute();
                $good = true;
                foreach ($query->fetchAll() as $column) {
                    if ($column["name"] === "money") $good = false;
                }  
                if ($good) {
                    $query = MainAPI::$PDO->prepare("ALTER TABLE ".FactionTable::TABLE_NAME." ADD `money` BIGINT NOT NULL DEFAULT '$defaultMoney';");
                    $query->execute(); 
                }
            }
        } catch (PDOException $exception) {
            $this->getLogger()->warning("An error has occurred in the initialization of " . $this->getExtensionName() . ". Automatic deactivation of the extension");
            $this->getLogger()->debug((string) $exception->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        Main::getInstance()->getExtensionManager()->registerExtension($this);
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
    public function getLangConfig(): array {
        return $this->LangConfig;
    }

    public function getConfigBank(): Config {
        return $this->config;
    }

    private function initConfigLang(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->saveResource('fr_FR.yml');
        $this->saveResource('en_EN.yml');
        $this->saveResource('config.yml');
        $this->config = new Config($this->getDataFolder() . "config.yml");
        $this->LangConfig = [
            "fr_FR" => new Config($this->getDataFolder() . "fr_FR.yml", Config::YAML),
            "en_EN" => new Config($this->getDataFolder() . "en_EN.yml", Config::YAML)
        ];
    }

    private function registerPermission(): void {
        $permissionManager = Main::getInstance()->getPermissionManager();
        $permissionManager->registerPermission(new Permission(
            "PERMISSION_BANK_DEPOSIT", 
            function(string $playerName) { return Utils::getText($playerName, "PERMISSION_BANK_DEPOSIT");}, 
            PermissionIdsBank::PERMISSION_BANK_DEPOSIT))
        ->registerPermission(new Permission(
            "PERMISSION_SEE_BANK_HISTORY", 
            function(string $playerName) { return Utils::getText($playerName, "PERMISSION_SEE_BANK_HISTORY");}, 
            PermissionIdsBank::PERMISSION_SEE_BANK_HISTORY));
    }

    private function registerRoute(): void {
        $routes = [
            MainBank::class,
            BankDeposit::class,
            BankHistory::class
        ];
        foreach ($routes as $route) {
            RouterFactory::registerRoute(new $route());
        }
    }

    private function registerCollection(): void {
        $ButtonCollection = CollectionFactory::get(MainCollectionFac::SLUG);
        $ButtonCollection->registerCallable("FactionMasterBank", function() use ($ButtonCollection) {
            $ButtonCollection->register(new Bank(), 1, true);
        });
        CollectionFactory::register(new CollectionMainBank());
        CollectionFactory::register(new HistoryBank());
    }

}