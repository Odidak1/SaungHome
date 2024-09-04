<?php

namespace Odidak\SaungHome;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;

class Main extends PluginBase {

    private Config $homes;
    private Config $config;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        if (!file_exists($this->getDataFolder() . "homes.yml")) {
            $this->saveResource("homes.yml");
        }

        $this->homes = new Config($this->getDataFolder() . "homes.yml", Config::YAML, []);
    }

    public function onDisable(): void {
        $this->homes->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§c[!] Command ini hanya bisa digunakan oleh pemain.");
            return true;
        }

        switch ($command->getName()) {
            case "sethome":
                if (count($args) < 1) {
                    $sender->sendMessage("§e/sethome [nama home]");
                    return false;
                }
                $homeName = strtolower($args[0]);

                if ($this->hasReachedHomeLimit($sender)) {
                    $sender->sendMessage("§c[!] Kamu telah mencapai batas maksimum limit.");
                    return true;
                }

                $this->setHome($sender, $homeName);
                return true;

            case "home":
                if (count($args) < 1) {
                    $sender->sendMessage("§e/home [nama home]");
                    return false;
                }
                $homeName = strtolower($args[0]);
                $this->goHome($sender, $homeName);
                return true;

            case "delhome":
                if (count($args) < 1) {
                    $sender->sendMessage("§e/delhome [nama home]");
                    return false;
                }
                $homeName = strtolower($args[0]);
                $this->deleteHome($sender, $homeName);
                return true;

            case "listhome":
                $this->showListForm($sender);
                return true;

            case "homelimit":
                $this->showLimitForm($sender);
                return true;

            default:
                return false;
        }
    }

    private function setHome(Player $player, string $homeName): void {
        $homeData = [
            "x" => $player->getPosition()->getX(),
            "y" => $player->getPosition()->getY(),
            "z" => $player->getPosition()->getZ(),
            "level" => $player->getWorld()->getFolderName()
        ];

        $allHomes = $this->homes->get($player->getName(), []);
        $allHomes[$homeName] = $homeData;

        $this->homes->set($player->getName(), $allHomes);
        $this->homes->save();
        $player->sendMessage("§aHome '{$homeName}' berhasil disimpan!");
    }

    private function goHome(Player $player, string $homeName): void {
        $allHomes = $this->homes->get($player->getName(), []);

        if (!isset($allHomes[$homeName])) {
            $player->sendMessage("§c[!] Home '{$homeName}' tidak ditemukan!");
            return;
        }

        $homeData = $allHomes[$homeName];
        $world = $this->getServer()->getWorldManager()->getWorldByName($homeData["level"]);

        if ($world === null) {
            $player->sendMessage("§c[!] World tidak ditemukan!");
            return;
        }

        $position = new Position($homeData["x"], $homeData["y"], $homeData["z"], $world);
        $player->teleport($position);
        $player->sendMessage("§aTeleport ke home '{$homeName}' berhasil!");
    }

    private function deleteHome(Player $player, string $homeName): void {
        $allHomes = $this->homes->get($player->getName(), []);

        if (!isset($allHomes[$homeName])) {
            $player->sendMessage("§c[!] Home '{$homeName}' tidak ditemukan!");
            return;
        }

        unset($allHomes[$homeName]);
        $this->homes->set($player->getName(), $allHomes);
        $this->homes->save();
        $player->sendMessage("§aHome '{$homeName}' berhasil dihapus!");
    }

    private function showListForm(Player $player): void {
        $allHomes = $this->homes->get($player->getName(), []);

        if (empty($allHomes)) {
            $player->sendMessage("§c[!] Kamu belum memiliki home yang disimpan.");
            return;
        }

        $form = new CustomForm(function(Player $player, $data) {});
        $form->setTitle("Daftar Home");
        $homeList = implode("\n", array_keys($allHomes));

        $form->addLabel("Daftar home yang kamu simpan:");
        $form->addLabel($homeList);

        $player->sendForm($form);
    }

    private function hasReachedHomeLimit(Player $player): bool {
        $limit = $this->getHomeLimit($player);
        $allHomes = $this->homes->get($player->getName(), []);
        return count($allHomes) >= $limit;
    }

    private function getHomeLimit(Player $player): int {
        $defaultLimit = $this->config->get("default-home-limit", 3);
        $limit = $defaultLimit;

        foreach ($this->config->get("permissions-limits", []) as $permission => $data) {
            if ($player->hasPermission($permission)) {
                $limit = (int)$data["limit"];
                if ($limit === -1) {
                    return PHP_INT_MAX;
                }
            }
        }

        return $limit;
    }

    private function showLimitForm(Player $player): void {
        $limit = $this->getHomeLimit($player);
        $allHomes = $this->homes->get($player->getName(), []);
        $used = count($allHomes);

        if ($limit === PHP_INT_MAX) {
            $limitText = "Unlimited";
            $availableText = "Unlimited";
        } else {
            $available = $limit - $used;
            $limitText = $limit;
            $availableText = $available;
        }

        $form = new CustomForm(function(Player $player, $data) {});
        $form->setTitle("Home Limit");
        $form->addLabel("Total limit yang didapatkan: " . $limitText);
        $form->addLabel("Total limit yang tersedia: " . $availableText);
        $form->addLabel("Total limit yang sudah dipakai: " . $used);

        $player->sendForm($form);
    }
}
