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

namespace ShockedPlot7560\FactionMasterBank\Listener;

use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use pocketmine\event\Listener;
use pocketmine\Player;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Database\Entity\FactionEntity;
use ShockedPlot7560\FactionMaster\Event\FactionCreateEvent;
use ShockedPlot7560\FactionMaster\Event\FactionDeleteEvent;
use ShockedPlot7560\FactionMaster\Event\FactionJoinEvent;
use ShockedPlot7560\FactionMaster\Event\FactionLeaveEvent;
use ShockedPlot7560\FactionMaster\Event\FactionLevelChangeEvent;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\Database\Entity\Money;
use ShockedPlot7560\FactionMasterBank\Event\MoneyChangeEvent;
use ShockedPlot7560\FactionMasterBank\FactionMasterBank;

class ScoreHudListener implements Listener {

    /** @var Main */
    private $Main;

    public function __construct(FactionMasterBank $Main) {
        $this->Main = $Main;
    }

    public function onTagResolve(TagsResolveEvent $event): void {
        $player = $event->getPlayer();
        $tag = $event->getTag();
        switch ($tag->getName()) {
            case "factionmaster.faction.money":
                $faction = MainAPI::getFactionOfPlayer($player->getName());
                if ($faction instanceof FactionEntity) {
                    $money = BankAPI::getMoney($faction->name);
                    if (!$money instanceof Money) return;
                    $tag->setValue($money->amount ?? 0);
                }else{
                    $tag->setValue(0);
                }
                break;
        }
    }

    public function onMoney(MoneyChangeEvent $event): void {
        $faction = $event->getFaction();
        $server = $this->Main->getServer();
        $money = BankAPI::getMoney($faction->name);
        if (!$money instanceof Money) return;
        foreach ($faction->members as $name => $rank) {
            $player = $server->getPlayer($name);
            if ($player instanceof Player) {  
                $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                    "factionmaster.faction.money",
                    $money->amount
                ));
                $ev->call();          
            }
        }
    }

    public function onFactionCreate(FactionCreateEvent $event): void {
        $player = $event->getPlayer();
        $Faction = MainAPI::getFaction($event->getFaction());
        if ($Faction instanceof FactionEntity) {
            $money = BankAPI::getMoney($Faction->name);
            if (!$money instanceof Money) return;
            $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                "factionmaster.faction.money",
                $money->amount
            ));
            $ev->call();
        }else{
            $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                "factionmaster.faction.money",
                0
            ));
            $ev->call();
        }
    }

    public function onFactionJoin(FactionJoinEvent $event): void {
        $player = $event->getPlayer();
        if (!$player instanceof Player) {
            $player =  $this->Main->getServer()->getPlayer($player);
        }
        if (!$player instanceof Player) return;
        $Faction = $event->getFaction();
        if ($Faction instanceof FactionEntity) {
            $money = BankAPI::getMoney($Faction->name);
            if (!$money instanceof Money) return;
            $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                "factionmaster.faction.money",
                $money->amount
            ));
            $ev->call();
        }else{
            $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                "factionmaster.faction.money",
                0
            ));
            $ev->call();
        }
    }

    public function onLevelChange(FactionLevelChangeEvent $event): void {
        $faction = $event->getFaction();
        $server = $this->Main->getServer();
        $money = BankAPI::getMoney($faction->name);
        if (!$money instanceof Money) return;
        foreach ($faction->members as $name => $rank) {
            $player = $server->getPlayer($name);
            if ($player instanceof Player) {  
                $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                    "factionmaster.faction.money",
                    $money->amount
                ));
                $ev->call();          
            }
        }
    }

    public function onFactionLeave(FactionLeaveEvent $event): void {
        $player = $event->getPlayer();
        $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
            "factionmaster.faction.money",
            0
        ));
        $ev->call();
    }

    public function onFactionDelete(FactionDeleteEvent $event): void {
        $server = $this->Main->getServer();
        foreach ($event->getFaction()->members as $name => $rank) {
            $player = $server->getPlayer($name);
            if ($player instanceof Player) {
                $ev = new PlayerTagUpdateEvent($player, new ScoreTag(
                    "factionmaster.faction.money",
                    0
                ));
                $ev->call();
            }
        }
    }
}