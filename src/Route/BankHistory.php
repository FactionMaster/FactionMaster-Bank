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

namespace ShockedPlot7560\FactionMasterBank\Route;

use pocketmine\player\Player;
use ShockedPlot7560\FactionMaster\Button\Collection\CollectionFactory;
use ShockedPlot7560\FactionMaster\Database\Entity\UserEntity;
use ShockedPlot7560\FactionMaster\Route\Route;
use ShockedPlot7560\FactionMaster\Route\RouteBase;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\Button\Collection\HistoryBank;
use ShockedPlot7560\FactionMasterBank\Database\Entity\BankHistory as EntityBankHistory;
use ShockedPlot7560\FactionMasterBank\FactionMasterBank;
use ShockedPlot7560\FactionMasterBank\PermissionIdsBank;
use ShockedPlot7560\FactionMaster\libs\Vecnavium\FormsUI\SimpleForm;
use function abs;
use function ceil;
use function count;
use function min;

class BankHistory extends RouteBase implements Route {
	const SLUG = "bankHistory";

	public function getSlug(): string {
		return self::SLUG;
	}

	public function getBackRoute(): ?Route {
		return RouterFactory::get(MainBank::SLUG);
	}

	public function getPermissions(): array {
		return [
			PermissionIdsBank::PERMISSION_SEE_BANK_HISTORY
		];
	}

	/**
	 * @param array $params First element -> transaction history, second -> currentpage
	 */
	public function __invoke(Player $player, UserEntity $userEntity, array $userPermissions, ?array $params = null) {
		$this->init($player, $userEntity, $userPermissions, $params);
		if (FactionMasterBank::getInstance()->getConfig()->get("bank-history") != true) {
			Utils::processMenu($this->getBackRoute(), $this->getPlayer());
			return;
		}

		$content = Utils::getText($this->getUserEntity()->getName(), "BANK_HISTORY_CONTENT");
		if (isset($params[0])) {
			/** @var EntityBankHistory[] */
			$histories = $params[0];
			$maxItemPerPage = FactionMasterBank::getInstance()->getConfig()->get("max-item-history");
			if (!empty($params[1])) {
				$currentPage = $params[1];
				if ($currentPage > ceil(count($histories) / $maxItemPerPage)) {
					$currentPage = ceil(count($histories) / $maxItemPerPage);
				}
			} else {
				$currentPage = 1;
			}
			for ($i=($currentPage - 1) * $maxItemPerPage; $i < min($currentPage * $maxItemPerPage, count($histories)); $i++) {
				$history = $histories[$i];
				if ($history->getType() == BankAPI::BANK_HISTORY_ADD_MODE) {
					$content .= "\n§r §7> §2+" . abs($history->getAmount()) . " §o§7: " . $history->getEntityString();
				} elseif ($history->getType() == BankAPI::BANK_HISTORY_REMOVE_MODE) {
					$content .= "\n§r §7> §4-" . abs($history->getAmount()) . " §o§7: " . $history->getEntityString();
				}
			}
			if (count($params[0]) == 0) {
				$content .= Utils::getText($this->getUserEntity()->getName(), "NO_TRANSACTION");
			}
		}
		$this->setCollection(CollectionFactory::get(HistoryBank::SLUG)->init($this->getPlayer(), $this->getUserEntity(), $params[0], $params[1]));
		$player->sendForm($this->getForm($content));
	}

	public function call() : callable {
		return function (Player $player, $data) {
			if ($data === null) {
				return;
			}
			$this->getCollection()->process($data, $player);
		};
	}

	private function getForm(string $message = ""): SimpleForm {
		$menu = new SimpleForm($this->call());
		$menu = $this->getCollection()->generateButtons($menu, $this->getUserEntity()->getName());
		$menu->setTitle(Utils::getText($this->getUserEntity()->getName(), "BANK_HISTORY_TITLE"));
		$menu->setContent($message);
		return $menu;
	}
}