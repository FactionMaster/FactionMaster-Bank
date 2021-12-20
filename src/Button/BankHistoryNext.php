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

namespace ShockedPlot7560\FactionMasterBank\Button;

use pocketmine\player\Player;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Button\Button;
use ShockedPlot7560\FactionMaster\Database\Entity\FactionEntity;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Task\DatabaseTask;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\Database\Entity\BankHistory as EntityBankHistory;
use ShockedPlot7560\FactionMasterBank\Database\Table\BankHistoryTable;
use ShockedPlot7560\FactionMasterBank\FactionMasterBank;
use ShockedPlot7560\FactionMasterBank\PermissionIdsBank;
use ShockedPlot7560\FactionMasterBank\Route\BankHistory as RouteBankHistory;

class BankHistoryNext extends Button {
	const SLUG = "bankHistoryNext";

	public function __construct(int $currentPage) {
		$this->setSlug(self::SLUG)
			->setContent(function(string $playerName) {
				return "Next";
			})
			->setCallable(function(Player $player) use ($currentPage) {
				$faction = MainAPI::getFactionOfPlayer($player->getName());
				if ($faction instanceof FactionEntity) {
					FactionMasterBank::getInstance()->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
						"SELECT * FROM " . BankHistoryTable::TABLE_NAME . " WHERE faction = :faction ORDER BY date DESC",
						[
							"faction" => $faction->name
						],
						function (array $result) use ($player, $currentPage) {
							var_dump("ok");
							Utils::processMenu(RouterFactory::get(RouteBankHistory::SLUG), $player, [$result, $currentPage + 1]);
						},
						EntityBankHistory::class
					));
				}
			})
			->setPermissions([
				PermissionIdsBank::PERMISSION_SEE_BANK_HISTORY
			]);
	}
}