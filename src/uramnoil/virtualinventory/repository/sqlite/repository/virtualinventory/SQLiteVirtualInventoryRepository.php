<?php


namespace uramnoil\virtualinventory\repository\sqlite\repository\virtualinventory;


use Closure;
use pocketmine\IPlayer;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Utils;
use uramnoil\virtualinventory\inventory\factory\VirtualChestInventoryFactory;
use uramnoil\virtualinventory\inventory\factory\VirtualDoubleChestInventoryFactory;
use uramnoil\virtualinventory\inventory\factory\VirtualInventoryFactory;
use uramnoil\virtualinventory\inventory\VirtualInventory;
use uramnoil\virtualinventory\repository\sqlite\repository\virtualinventory\dao\virtualinventory\SQLiteVirtualInventoryDAO;
use uramnoil\virtualinventory\repository\sqlite\repository\virtualinventory\dao\virtualinventory\VirtualInventoryDAO;
use uramnoil\virtualinventory\extension\InventoryConverterTrait;
use uramnoil\virtualinventory\extension\SchedulerTrait;
use uramnoil\virtualinventory\task\TransactionTask;
use uramnoil\virtualinventory\VirtualInventoryPlugin;
use function array_merge;

class SQLiteVirtualInventoryRepository implements VirtualInventoryRepository {
	use InventoryConverterTrait;

	/** @var VirtualInventoryFactory[] */
	private $factories = [];
	/** @var VirtualInventory[] */
	private $cachedInventories = [];
	/** @var VirtualInventoryPlugin  */
	private $plugin;
	/** @var VirtualInventoryDAO */
	private $dao;

	public function __construct(PluginBase $plugin) {	//OPTIMIZE	ファイルの保存場所さえ得られればいい
		$this->plugin = $plugin;

		$this->factories[InventoryIds::INVENTORY_TYPE_CHEST]        = new VirtualChestInventoryFactory($this);
		$this->factories[InventoryIds::INVENTORY_TYPE_DOUBLE_CHEST] = new VirtualDoubleChestInventoryFactory($this);
	}

	public function open() : void {
		$this->dao->open();

	}

	public function close() : void {
		$this->dao->close();
	}

	public function save(VirtualInventory $inventory) : void {
		$this->dao->update($inventory->getId(), $this->itemsToRaw($inventory->getContents(true)));
	}


	public function new(IPlayer $owner, int $inventoryType = InventoryIds::INVENTORY_TYPE_CHEST, ?Closure $onDone = null) : VirtualInventory {
		$inventoryRaw = $this->dao->create($owner->getName(), $inventoryType);	//OPTIMIZE	ルールが散らばってる
		return $this->factories[$inventoryType]->createFrom($inventoryRaw['inventory_id'], $owner);
	}

	public function findByOwner(IPlayer $owner) : array{
		$idsNotIn = [];
		$cachedInventories = [];
		foreach($this->cachedInventories as $inventory) {
			if($inventory->getOwner()->getName() === $owner->getName()) {
				$cachedInventories[$inventory->getId()] = $inventory;
				$idsNotIn[$inventory->getId()] = $inventory;
			}
		}
		$inventoryRaws = $this->dao->findByOwner($owner->getName(), [VirtualInventoryDAO::OPTION_IDS_NOT_IN => $idsNotIn]);

		$inventories = [];

		foreach($inventoryRaws as $inventoryRaw) {
			$inventory = $this->factories[$inventoryRaw['inventory_type']]
				->createFrom(
					$inventoryRaw['inventory_id'],
					Server::getInstance()->getOfflinePlayer($inventoryRaw['owner_name'])
				);
			$inventory->setContents($this->rawToItems($inventoryRaw['items']));
			$inventories[$inventory->getId()] = $inventory;
		}

		return array_merge($cachedInventories, $inventories);
	}

	public function findById(int $id) : VirtualInventory{
		if(isset($this->cachedInventories[$id])) return $this->cachedInventories[$id];

		$inventoryRaw = $this->dao->findById($id);
		if($inventoryRaw !== null) return null;

		$inventory = $this->factories[$inventoryRaw['inventory_type']]
			->createFrom(
				$inventoryRaw['inventory_id'],
				Server::getInstance()->getOfflinePlayer($inventoryRaw['owner_name'])
			);
		$inventory->setContents($this->rawToItems($inventoryRaw['items']));
		return $inventory;
	}

	public function delete(VirtualInventory $inventory) : void {
		$this->dao->delete($inventory->getId());
		$inventory->onDelete();
		unset($this->cachedInventories[$inventory->getId()]);
	}
}