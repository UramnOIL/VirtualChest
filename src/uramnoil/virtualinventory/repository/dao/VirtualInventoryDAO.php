<?php


namespace uramnoil\virtualinventory\repository\dao;

interface VirtualInventoryDAO extends DAO {
	public const OPTION_IDS_NOT_IN = 0;

	/**
	 * @param  string  $ownerName
	 * @param  int  $type
	 * @param  string  $title
	 *
	 * @return array
	 */
	public function create(string $ownerName, int $type, string $title) : array;

	/**
	 * @param  int  $id
	 *
	 * @return array
	 */
	public function findById(int $id) : array;

	/**
	 * @param  string  $name
	 * @param  array  $option
	 *
	 * @return array
	 */
	public function findByOwner(string $name, array $option = []) : array;

	/**ss
	 *
	 * @param  int  $id
	 */
	public function delete(int $id) : void;

	/**
	 * @param  array  $inventoryRawRaw
	 */
	public function update(array $inventoryRawRaw) : void;
}