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

namespace ShockedPlot7560\FactionMasterBank\API;

use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Database\Table\FactionTable;
use ShockedPlot7560\FactionMaster\Main;
use ShockedPlot7560\FactionMaster\Task\DatabaseTask;
use ShockedPlot7560\FactionMasterBank\Database\Table\BankHistoryTable;

class BankAPI {

    const BANK_HISTORY_ADD_MODE = 0;
    const BANK_HISTORY_REMOVE_MODE = 1;

    const BANK_HISTORY_QUERY = "SELECT * FROM " . BankHistoryTable::TABLE_NAME . " WHERE faction = :faction ORDER BY date DESC";

    /**
     * @param int $money Can be negative to remove money
     */
    public static function updateMoney(string $factionName, int $money, string $reason = "No reason"): void {
        $Faction = MainAPI::getFaction($factionName);
        $Faction->money += $money;
        Main::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
            "UPDATE " . FactionTable::TABLE_NAME . " SET money = money + :money WHERE name = :name",
            [
                "money" => $money,
                "name" => $factionName
            ],
            function () use ($Faction) {
                MainAPI::$factions[$Faction->name] = $Faction;
            }
        ));
        if ($money < 0) {
            $type = self::BANK_HISTORY_REMOVE_MODE;
        }else{
            $type = self::BANK_HISTORY_ADD_MODE;
        }
        Main::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
            "INSERT INTO " . BankHistoryTable::TABLE_NAME . " (faction, entity, amount, type) VALUE (:faction, :player, :amount, :type)",
            [
                'faction' => $factionName,
                'player' => $reason,
                'amount' => $money, 
                'type' => $type
            ],
            function () { }
        ));
    }
}