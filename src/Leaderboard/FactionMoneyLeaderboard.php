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
namespace ShockedPlot7560\FactionMasterBank\Leaderboard;

use pocketmine\utils\Config;
use pocketmine\world\particle\FloatingTextParticle;
use ShockedPlot7560\FactionMaster\Database\Entity\FactionEntity;
use ShockedPlot7560\FactionMaster\Leaderboard\EntityLeaderboard;
use ShockedPlot7560\FactionMaster\Manager\ConfigManager;
use ShockedPlot7560\FactionMaster\Manager\LeaderboardManager;
use ShockedPlot7560\FactionMaster\Task\DatabaseTask;
use ShockedPlot7560\FactionMaster\Utils\Leaderboard;
use ShockedPlot7560\FactionMasterBank\Database\Entity\Money;
use ShockedPlot7560\FactionMasterBank\Database\Table\MoneyTable;

use function str_replace;

class FactionMoneyLeaderboard extends EntityLeaderboard {
	const SLUG = "factionMoney";

	public function getSqlQuery(): string {
		return "SELECT * FROM " . MoneyTable::TABLE_NAME . " ORDER BY amount DESC LIMIT 10";
	}

	public function getSlug(): string {
		return self::SLUG;
	}

	public function getConfig(): Config {
		return ConfigManager::getConfig();
	}

	public function place(Leaderboard $leaderboard, ?array $players = null): void {
		$this->main->getServer()->getAsyncPool()->submitTask(new DatabaseTask(
			$this->getSqlQuery(),
			[],
			function (array $result) use ($leaderboard, $players) {
				/** @var Money[] $result */
				$nametag = $leaderboard->getHeaderLign() . "\n";
				foreach ($result as $money) {
					$newLine = $leaderboard->getBodyLign();
					$faction = $money->getFactionEntity();
					if ($faction instanceof FactionEntity) {
						$newLine = str_replace(["{factionName}", "{level}", "{power}", "{money}"], [$faction->getName(), $faction->getLevel(), $faction->getPower(), $money->getAmount()], $newLine);
						$nametag .= $newLine . "\n";
					}
				}
				$particule = new FloatingTextParticle($nametag);
				LeaderboardManager::addSession($leaderboard->getRawCoordonate(), $particule);
				$leaderboard->getWorld()->addParticle(
					$leaderboard->getVector3(),
					$particule,
					$players
				);
			},
			Money::class
		));
	}
}