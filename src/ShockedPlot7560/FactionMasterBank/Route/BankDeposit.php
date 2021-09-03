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

use jojoe77777\FormAPI\CustomForm;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Database\Entity\UserEntity;
use ShockedPlot7560\FactionMaster\Route\Route;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Task\MenuSendTask;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\PermissionIdsBank;

class BankDeposit implements Route {

    const SLUG = "bankDeposit";

    public $PermissionNeed = [
        PermissionIdsBank::PERMISSION_BANK_DEPOSIT
    ];

    /** @var Route */
    private $backMenu;
    /** @var UserEntity */
    private $UserEntity;

    public function getSlug(): string {
        return self::SLUG;
    }

    public function __construct() {
        $this->backMenu = RouterFactory::get(MainBank::SLUG);
    }

    public function __invoke(Player $player, UserEntity $User, array $UserPermissions, ?array $params = null) {
        $this->UserEntity = $User;
        $message = "";
        if (isset($params[0]) && \is_string($params[0])) $message = $params[0];
        $player->sendForm($this->bankDeposit($message));;
    }

    public function call() : callable{
        $backRoute = $this->backMenu;
        return function (Player $Player, $data) use ($backRoute) {
            if ($data === null) return;
            if ($data[1] !== "") {
                $suggest = (int) $data[1];
                if ($suggest > 0) {
                    $factionName = MainAPI::getUser($Player->getName())->faction;
                    $moneyInstance = BankAPI::getMoney($factionName);
                    $money = EconomyAPI::getInstance()->myMoney($Player->getName());
                    if ($money - $suggest < 0) {
                        Utils::processMenu(RouterFactory::get(self::SLUG), $Player, [Utils::getText($Player->getName(), "NO_ENOUGH_MONEY")]);
                        return;
                    }
                    if (EconomyAPI::getInstance()->reduceMoney($Player->getName(), $suggest) == EconomyAPI::RET_SUCCESS) {
                        BankAPI::updateMoney($factionName, $suggest, $Player->getName());
                        Utils::newMenuSendTask(new MenuSendTask(
                            function () use ($factionName, $moneyInstance, $suggest) {
                                $newMoney = $moneyInstance->amount + $suggest;
                                return BankAPI::getMoney($factionName)->amount + $suggest == $newMoney;
                            },
                            function () use ($backRoute, $Player, $data, $suggest) {
                                Utils::processMenu($backRoute, $Player, [Utils::getText($Player->getName(), "SUCCESS_BANK_DEPOSIT", ["money" => $data[1]])]);
                            },
                            function () use ($Player) {
                                Utils::processMenu(RouterFactory::get(self::SLUG), $Player, [Utils::getText($Player->getName(), "ERROR")]);
                            }
                        ));                    
                    }
                }else{
                    Utils::processMenu(RouterFactory::get(self::SLUG), $Player, [Utils::getText($Player->getName(), "VALID_FORMAT")]);
                }
            }else{
                Utils::processMenu($backRoute, $Player);
            }
        };
    }

    private function bankDeposit(string $message = "") : CustomForm {
        $menu = new CustomForm($this->call());
        $menu->setTitle(Utils::getText($this->UserEntity->name, "BANK_DEPOSIT_TITLE"));
        $menu->addLabel($message);
        $menu->addInput(Utils::getText($this->UserEntity->name, "BANK_DEPOSIT_INPUT"));
        return $menu;
    }
}