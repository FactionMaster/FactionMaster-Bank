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

use PDO;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Task\DatabaseTask;
use ShockedPlot7560\FactionMasterBank\Database\Entity\Money;
use ShockedPlot7560\FactionMasterBank\Database\Table\BankHistoryTable;
use ShockedPlot7560\FactionMasterBank\Database\Table\MoneyTable;
use ShockedPlot7560\FactionMasterBank\FactionMasterBank;

class BankAPI {

    const BANK_HISTORY_ADD_MODE = 0;
    const BANK_HISTORY_REMOVE_MODE = 1;

    const BANK_HISTORY_QUERY = "SELECT * FROM " . BankHistoryTable::TABLE_NAME . " WHERE faction = :faction ORDER BY date DESC";

    /** @var Money[] */
    public static $money = [];

    public static function init() {
        try {
            $query = MainAPI::$PDO->prepare("SELECT * FROM " . MoneyTable::TABLE_NAME);
            $query->execute();
            $query->setFetchMode(PDO::FETCH_CLASS, Money::class);
            /** @var Money[] */
            $result = $query->fetchAll();
            foreach ($result as $money) {
                self::$money[$money->faction] = $money;
            }
        } catch (\PDOException $Exception) {
            return;
        }
    }

    public static function getMoney(string $factionName): ?Money {
        return self::$money[$factionName] ?? null;
    }

    /**
     * @param int $money Can be negative to remove money
     */
    public static function updateMoney(string $factionName, int $money, string $reason = "No reason"): void {
        if (($moneyInstance = self::getMoney($factionName)) instanceof Money) {
            $moneyInstance->amount += $money;
            FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
                "UPDATE " . MoneyTable::TABLE_NAME . " SET amount = :amount WHERE faction = :faction",
                [
                    "amount" => $moneyInstance->amount,
                    "faction" => $factionName
                ],
                function () use ($moneyInstance, $money, $reason) {
                    BankAPI::$money[$moneyInstance->faction] = $moneyInstance;
                    BankAPI::insertHistory($moneyInstance->faction, $money, $reason);    
                }
            ));
        }
    }

    public static function insertHistory(string $factionName, int $amount, string $reason, int $type = null) {
        if ($type === null) {
            if ($amount < 0) {
                $type = self::BANK_HISTORY_REMOVE_MODE;
            }else{
                $type = self::BANK_HISTORY_ADD_MODE;
            }
        }
        FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
            "INSERT INTO " . BankHistoryTable::TABLE_NAME . " (faction, entity, amount, type) VALUES (:faction, :player, :amount, :type)",
            [
                'faction' => $factionName,
                'player' => $reason,
                'amount' => $amount, 
                'type' => $type
            ],
            function () { }
        ));  
    }

    public static function initMoney(string $factionName): void {
        FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
            "INSERT INTO " . MoneyTable::TABLE_NAME . " (faction) VALUES (:faction)",
            [
                "faction" => $factionName
            ],
            function () use ($factionName) {
                FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(
                    new DatabaseTask(
                        "SELECT * FROM " . MoneyTable::TABLE_NAME . " WHERE faction = :faction", 
                        [ "faction" => $factionName ],
                        function ($result) use ($factionName) {
                            BankAPI::$money[$factionName] = $result[0] ?? null;
                        },
                        Money::class
                ));
            }
        ));
    }
}