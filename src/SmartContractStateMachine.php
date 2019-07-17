<?php
// MIT License
//
// Copyright (c) 2019 Just4Fun
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//

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
