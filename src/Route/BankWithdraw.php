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

use ShockedPlot7560\FactionMaster\libs\Vecnavium\FormsUI\CustomForm;
use onebone\economyapi\EconomyAPI;
use pocketmine\player\Player;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Database\Entity\UserEntity;
use ShockedPlot7560\FactionMaster\Route\Route;
use ShockedPlot7560\FactionMaster\Route\RouteBase;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Task\MenuSendTask;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\Event\MoneyChangeEvent;
use ShockedPlot7560\FactionMasterBank\FactionMasterBank;
use ShockedPlot7560\FactionMasterBank\PermissionIdsBank;

class BankWithdraw extends RouteBase implements Route {

    const SLUG = "bankWithdraw";

    public function getSlug(): string {
        return self::SLUG;
    }

    public function getBackRoute(): ?Route {
        return RouterFactory::get(MainBank::SLUG);
    }

    public function getPermissions(): array {
        return [
            PermissionIdsBank::PERMISSION_BANK_WITHDRAW
        ];
    }

    public function __invoke(Player $player, UserEntity $userEntity, array $userPermissions, ?array $params = null) {
        $this->init($player, $userEntity, $userPermissions, $params);
        if (FactionMasterBank::getInstance()->getConfig()->get("bank-history") != true) {
            Utils::processMenu($this->getBackRoute(), $this->getPlayer());
            return;
        }
        $message = "";
        if (isset($params[0]) && \is_string($params[0])) $message = $params[0];
        $player->sendForm($this->getForm($message));;
    }

    public function call() : callable{
        return function (Player $player, $data) {
            if ($data === null) return;
            if ($data[1] !== "") {
                $suggest = (int) $data[1];
                if ($suggest > 0) {
                    $factionName = MainAPI::getUser($player->getName())->getFactionName();
                    $moneyInstance = BankAPI::getMoney($factionName);
                    if ($moneyInstance->getAmount() - $suggest < 0) {
                        Utils::processMenu(RouterFactory::get(self::SLUG), $player, [Utils::getText($player->getName(), "NO_ENOUGH_MONEY_FACTION")]);
                        return;
                    }
                    if (EconomyAPI::getInstance()->addMoney($player->getName(), $suggest) == EconomyAPI::RET_SUCCESS) {
                        BankAPI::updateMoney($factionName, $suggest * -1, $player->getName());
                        Utils::newMenuSendTask(new MenuSendTask(
                            function () use ($factionName, $moneyInstance, $suggest) {
                                $newMoney = $moneyInstance->getAmount() - $suggest;
                                return BankAPI::getMoney($factionName)->getAmount() - $suggest == $newMoney;
                            },
                            function () use ($player, $data) {
                                (new MoneyChangeEvent(MainAPI::getFactionOfPlayer($player->getName()), (int) $data[1] * -1))->call();
                                Utils::processMenu($this->getBackRoute(), $player, [Utils::getText($player->getName(), "SUCCESS_BANK_WITHDRAW", ["money" => $data[1]])]);
                            },
                            function () use ($player) {
                                Utils::processMenu(RouterFactory::get(self::SLUG), $player, [Utils::getText($player->getName(), "ERROR")]);
                            }
                        ));                    
                    }
                }else{
                    Utils::processMenu(RouterFactory::get(self::SLUG), $player, [Utils::getText($player->getName(), "VALID_FORMAT")]);
                }
            }else{
                Utils::processMenu($this->getBackRoute(), $player);
            }
        };
    }

    private function getForm(string $message = "") : CustomForm {
        $menu = new CustomForm($this->call());
        $menu->setTitle(Utils::getText($this->getUserEntity()->getName(), "BANK_WITHDRAW_TITLE"));
        $menu->addLabel($message);
        $menu->addInput(Utils::getText($this->getUserEntity()->getName(), "BANK_WITHDRAW_INPUT"));
        return $menu;
    }
}