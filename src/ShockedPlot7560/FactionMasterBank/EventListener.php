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

use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Furnace;
use pocketmine\block\ItemFrame;
use pocketmine\block\Trapdoor;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\item\Bucket;
use pocketmine\item\Hoe;
use pocketmine\item\ItemIds;
use pocketmine\item\Shovel;
use pocketmine\level\format\Chunk;
use pocketmine\math\Vector3;
use pocketmine\Player;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Database\Entity\FactionEntity;
use ShockedPlot7560\FactionMaster\Database\Entity\InvitationEntity;
use ShockedPlot7560\FactionMaster\Database\Entity\UserEntity;
use ShockedPlot7560\FactionMaster\Database\Table\FactionTable;
use ShockedPlot7560\FactionMaster\Database\Table\InvitationTable;
use ShockedPlot7560\FactionMaster\Database\Table\UserTable;
use ShockedPlot7560\FactionMaster\Event\FactionCreateEvent;
use ShockedPlot7560\FactionMaster\Event\FactionDeleteEvent;
use ShockedPlot7560\FactionMaster\Main;
use ShockedPlot7560\FactionMaster\Task\DatabaseTask;
use ShockedPlot7560\FactionMaster\Task\MenuSendTask;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\Database\Entity\Money;
use ShockedPlot7560\FactionMasterBank\Database\Table\BankHistoryTable;
use ShockedPlot7560\FactionMasterBank\Database\Table\MoneyTable;

class EventListener implements Listener {

    /** @var Main */
    private $Main;

    public function __construct(FactionMasterBank $Main) {
        $this->Main = $Main;
    }

    public function onFaction(FactionCreateEvent $event) {
        BankAPI::initMoney($event->getFaction());
    }

    public function onFactionDelete(FactionDeleteEvent $event) {
        FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
            "DELETE FROM " . MoneyTable::TABLE_NAME . " WHERE faction = :faction",
            ["faction" => $event->getFaction()->name],
            function () use ($event) {
                unset(BankAPI::$money[$event->getFaction()->name]);
            }
        ));
        FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
            "DELETE FROM " . BankHistoryTable::TABLE_NAME . " WHERE faction = :faction",
            ["faction" => $event->getFaction()->name],
            function () use ($event) { }
        ));
    }

    public function onJoin(PlayerLoginEvent $event) {
        $playerName = $event->getPlayer()->getName();
        Utils::newMenuSendTask(new MenuSendTask(
            function () use ($playerName) {
                return MainAPI::getUser($playerName) instanceof UserEntity;
            },
            function () use ($playerName, $event) {
                $user = MainAPI::getUser($playerName);
                if ($user->faction !== null) {
                    if (!BankAPI::getMoney($user->faction) instanceof Money) {
                        BankAPI::initMoney($user->faction);
                    }
                    Utils::newMenuSendTask(new MenuSendTask(
                        function () use ($user) {
                            return BankAPI::getMoney($user->faction) instanceof Money;
                        },
                        function () use ($user) {
                            Main::getInstance()->getServer()->getAsyncPool()->submitTask(
                                new DatabaseTask(
                                    "SELECT * FROM " . MoneyTable::TABLE_NAME . " WHERE faction = :faction", 
                                    [
                                        "faction" => $user->faction
                                    ],
                                    function ($result) use ($user) {
                                        $money = $result[0];
                                        BankAPI::$money[$money->faction] = $money;
                                    },
                                    Money::class
                            ));   
                        },
                        function () use ($event) {
                            $event->getPlayer()->kick(Utils::getText($event->getPlayer()->getName(), "ERROR_DATA_SAVING"), false);
                        }
                    ));
                }
            },
            function () use ($event) {
                $event->getPlayer()->kick(Utils::getText($event->getPlayer()->getName(), "ERROR_DATA_SAVING"), false);
            }
        ));
        return;
    }
}