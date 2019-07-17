<?php
// Copyright 2018 MaTaXeToS
// Copyright 2019 The Just4Fun Authors
// This file is part of the J4FCore library.
//
// The J4FCore library is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// The J4FCore library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with the J4FCore library. If not, see <http://www.gnu.org/licenses/>.

class SmartContractStateMachine {

	public $config = [];
	public $dataDirectory = '';
	public $storePath = '';

	// Initialize the state.
	function __construct( $dataDir = '', $configurations = [] ) {
		// Define the root path of State.
		$this->root = __DIR__;
		// Add data dir.
		$configurations[ 'data_directory' ] = $dataDir;

		$this->config = $configurations;

		// Initialize NoSQL
		$this->init();
	}

	// Initialize the store.
	public static function store($contractHash = false, $dataDir, $options = false ) {
		if ( !$contractHash OR empty( $contractHash ) ) throw new \Exception( 'ContractHash was not valid' );
		$_dbInstance = new self($dataDir, $options);
		$_dbInstance->contractHash = $contractHash;

		//initStore
		$_dbInstance->initStore();
		return $_dbInstance;
	}

	// Return last store object.
	public function last() {
		$fetchedData = $this->getStateById($this->getLastState());
		return $fetchedData;
	}

	// Creates a new object in the store.
	// The object is a plaintext JSON document.
	public function insert($txnHash='',$state = false) {
		// Handle invalid data
		if (!$state OR empty($state)) throw new \Exception( 'No state found to store' );

		// Make sure that the data is an array
		if (!is_array($state)) throw new \Exception( 'Storable state must an array' );

		try {
			$executed = $this->writeState($txnHash,$state);
		}
		catch (Exception $e) {
			$executed = $e->getMessage();
		}

		return $executed;
	}

	// Deletes a store and wipes all the data and cache it contains.
    public function reverseState() {
		$statesDir = $this->storePath.DIRECTORY_SEPARATOR.'states'.DIRECTORY_SEPARATOR;
		$h = opendir($statesDir);
		while (false !== ($fileName = readdir($h))) {
			if ($fileName == '.' || $fileName == '..') continue;
		    $allFiles[$fileName] = @stat($statesDir.$fileName)['ctime'];
		}
		if (!empty($allFiles)) {
			asort($allFiles);
			$allFiles = array_reverse($allFiles);

			$i = 0;
			foreach ($allFiles as $file=>$fileTime) {
				//Remove last state
				if ($i == 0)
					@unlink($statesDir.$file);

				//Remove last state
				else if ($i == 1) {
					$file = str_replace('.sdb','',$file);
					$this->setLastSate($file);
				}

				else
					break;

				$i++;
			}
		}
	}

    // Deletes a store and wipes all the data and cache it contains.
    public function deleteStates() {
		$it = new \RecursiveDirectoryIterator( $this->storePath, \RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new \RecursiveIteratorIterator( $it, \RecursiveIteratorIterator::CHILD_FIRST );
		foreach( $files as $file ) {
			if ($file->isDir()) @rmdir($file->getRealPath());
			else @unlink($file->getRealPath());
		}
		return @rmdir($this->storePath);
	}


	private function init() {

		// Check for valid configurations.
		if (empty($this->config) OR !is_array($this->config)) throw new \Exception('Invalid configurations was found.');

		// Check if the 'data_directory' was provided.
		if (!isset($this->config['data_directory'])) throw new \Exception('"data_directory" was not provided in the configurations.');

		// Check if data_directory is empty.
		if (empty($this->config['data_directory'])) throw new \Exception('"data_directory" cant be empty in the configurations.');

		// Prepare the data directory.
		$dataDir = trim($this->config['data_directory']);

		// Check if the data_directory exists.
		if (!file_exists($dataDir)) {
			// The directory was not found, create one.
			if (!mkdir($dataDir, 0777, true)) throw new \Exception('Unable to create the data directory at ' . $dataDir);
		}

		// Check if PHP has write permission in that directory.
		if (!is_writable($dataDir)) throw new \Exception('Data directory is not writable at "' . $dataDir . '." Please change data directory permission.');

		// Finally check if the directory is readable by PHP.
		if (!is_readable($dataDir)) throw new \Exception('Data directory is not readable at "' . $dataDir . '." Please change data directory permission.');

		// Set the data directory.
		$this->dataDirectory = $dataDir;
	} // End of init()

	// Method to init a store.
	private function initStore() {

		$store = trim($this->contractHash);

		// Validate the store name.
		if (!$store || empty($store)) throw new \Exception('Invalid store name was found');

		// Store directory path.
		$this->storePath = $this->dataDirectory . DIRECTORY_SEPARATOR . $store . DIRECTORY_SEPARATOR;

		// Check if the store exists.
		if (!file_exists($this->storePath)) {

			// The directory was not found, create one with cache directory.
			if (!mkdir($this->storePath, 0777, true)) throw new \Exception('Unable to create the store path at ' . $this->storePath);

			// Create the data directory.
			if (!mkdir($this->storePath.'states'.DIRECTORY_SEPARATOR, 0777, true)) throw new \Exception('Unable to create the states data directory at ' . $this->storePath.DIRECTORY_SEPARATOR.'states');
			// Create the store counter file.
			if (!file_put_contents($this->storePath.'state.sdb', '0')) throw new \Exception('Unable to create the system counter for the store! Please check write permission');
		}
		// Check if PHP has write permission in that directory.
		if (!is_writable($this->storePath)) throw new \Exception('Store path is not writable at "' . $this->storePath . '." Please change store path permission.');
		// Finally check if the directory is readable by PHP.
		if (!is_readable($this->storePath)) throw new \Exception('Store path is not readable at "' . $this->storePath . '." Please change store path permission.');
	}

	private function setLastSate($txnHash) {
		$counterPath = $this->storePath.'state.sdb';
		@file_put_contents($counterPath, $txnHash);
		return $txnHash;
	}
	// Return the last created store object ID.
	private function getLastState() {
		$counterPath = $this->storePath.'state.sdb';
		if (file_exists($counterPath)) {
			return @file_get_contents($counterPath);
		}
	}
	// Get a store by its system id. "_id"
	private function getStateById($id) {
		$stateFile = $this->storePath . 'states' . DIRECTORY_SEPARATOR . $id . '.sdb';
		if (file_exists($stateFile)) {
			$state = @json_decode(@gzuncompress(@hex2bin(@file_get_contents($stateFile))), true);
			if ($state !== false) return $state;
		}
		return [];
	}

	// Writes an object in a store.
	private function writeState($txnHash,$state) {
		// Cast to array
		$state = (array) $state;
		// Check if it has _id key
		if (isset($state['_id'])) throw new \Exception('The _id index is reserved by SleekDB, please delete the _id key and try again');
		$id = $this->setLastSate($txnHash);

		// Add the system ID with the store state array.
		$state['_id'] = $id;

		$stateToStore = @bin2hex(@gzcompress(@json_encode($state), 9));
		if ($stateToStore === false) throw new \Exception('Unable to encode the data array, please provide a valid PHP associative array');

		// Define the store path
		$storePath = $this->storePath . DIRECTORY_SEPARATOR . 'states'. DIRECTORY_SEPARATOR . $txnHash . '.sdb';
		if (@file_exists($storePath)) throw new \Exception('Can`t overwrite a state');
		if (!@file_put_contents($storePath, $stateToStore)) {
			throw new \Exception("Unable to write the object file! Please check if PHP has write permission.");
		}
		return true;
	}


}
