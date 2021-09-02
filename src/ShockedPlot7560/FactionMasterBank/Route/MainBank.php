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
use ShockedPlot7560\FactionMaster\API\MainAPI;
use ShockedPlot7560\FactionMaster\Button\Collection\Collection;
use ShockedPlot7560\FactionMaster\Button\Collection\CollectionFactory;
use ShockedPlot7560\FactionMaster\Database\Entity\UserEntity;
use ShockedPlot7560\FactionMaster\Route\MainPanel;
use ShockedPlot7560\FactionMaster\Route\Route;
use ShockedPlot7560\FactionMaster\Route\RouterFactory;
use ShockedPlot7560\FactionMaster\Utils\Utils;
use ShockedPlot7560\FactionMasterBank\Button\Collection\MainBank as CollectionMainBank;
use ShockedPlot7560\FactionMasterBank\PermissionIdsBank;

class MainBank implements Route {

    const SLUG = "mainBank";

    public $PermissionNeed = [
        PermissionIdsBank::PERMISSION_BANK_DEPOSIT, 
        PermissionIdsBank::PERMISSION_SEE_BANK_HISTORY
    ];
    
    /** @var Route */
    private $back;
    /** @var UserEntity */
    private $UserEntity;
    /** @var Collection */
    private $Collection;

    public function getSlug(): string {
        return self::SLUG;
    }

    public function __construct() {
        $this->back = RouterFactory::get(MainPanel::SLUG);
    }

    public function __invoke(Player $Player, UserEntity $User, array $UserPermissions, ?array $params = null) {
        $this->UserEntity = $User;
        $Faction = MainAPI::getFactionOfPlayer($Player->getName());
        $message = "";
        if (isset($params[0])) $message = (string) $params[0];
        $message .= (($message === "") ? "" : "\n \n") . Utils::getText($Player->getName(), "BANK_MAIN_CONTENT", ['money' => $Faction->money]);
        $this->Collection = CollectionFactory::get(CollectionMainBank::SLUG)->init($Player, $User);
        $Player->sendForm($this->menu($message));
    }

    public function call(): callable {
        $Collection = $this->Collection;
        return function (Player $player, $data) use ($Collection) {
            if ($data === null) return;
            $Collection->process($data, $player);
        };
    }

    private function menu(string $message = ""): SimpleForm {
        $menu = new SimpleForm($this->call());
        $menu = $this->Collection->generateButtons($menu, $this->UserEntity->name);
        $menu->setTitle(Utils::getText($this->UserEntity->name, "BANK_MAIN_PANEL_TITLE"));
        if ($message !== "") $menu->setContent($message);
        return $menu;
    }

}