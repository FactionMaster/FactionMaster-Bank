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

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\Player;
use ShockedPlot7560\FactionMaster\Button\Collection\CollectionFactory;
use ShockedPlot7560\FactionMaster\Database\Entity\UserEntity;
use ShockedPlot7560\FactionMaster\Route\Route;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\API\BankAPI;
use ShockedPlot7560\FactionMasterBank\Button\Collection\HistoryBank;
use ShockedPlot7560\FactionMasterBank\PermissionIdsBank;

class BankHistory implements Route {

    const SLUG = "bankHistory";

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

    public function __invoke(Player $Player, UserEntity $User, array $UserPermissions, ?array $params = null) {
        $this->UserEntity = $User;
        $content = Utils::getText($User->name, "BANK_HISTORY_CONTENT");
        if (isset($params[0])) {
            foreach ($params[0] as $history) {
                if($history->type == BankAPI::BANK_HISTORY_ADD_MODE) {
                    $content .= "\n§r §7> §2+".$history->amount." §o§7: ".$history->entity;
                }else if($history->type == BankAPI::BANK_HISTORY_REMOVE_MODE) {
                    $content .= "\n§r §7> §4-".$history->amount." §o§7: ".$history->entity;
                }
            } 
            if (count($params[0]) == 0) {
                $content .= Utils::getText($User->name, "NO_TRANSACTION");
            }       
        }
        $this->Collection = CollectionFactory::get(HistoryBank::SLUG)->init($Player, $User);
        $Player->sendForm($this->bankHistory($content));;
    }

    public function call() : callable{
        $backRoute = $this->backMenu;
        return function (Player $Player, $data) use ($backRoute) {
            if ($data === null) return;
            Utils::processMenu($backRoute, $Player);
        };
    }

    private function bankHistory(string $message = ""): SimpleForm {
        $menu = new SimpleForm($this->call());
        $menu = $this->Collection->generateButtons($menu, $this->UserEntity->name);
        $menu->setTitle(Utils::getText($this->UserEntity->name, "BANK_HISTORY_TITLE"));
        $menu->setContent($message);
        return $menu;
    }
}