<?php
// Copyright 2018 MaTaXeToS
// Copyright 2019-2020 The Just4Fun Authors
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

class DBBase extends DBBlocks {

    /**
     * @param string $table
     * @return bool
     */
    public function truncate(string $table) : bool {
        if ($this->db->query("TRUNCATE TABLE " . $table.";"))
            return true;
        return false;
    }

	/**
     * Check if have RocksDB Engine installed on MariaDB Server
     *
     * @return bool`
     */
    public function HaveRocksDBEngine() : bool {
		try {
			$query = $this->db->query("SHOW ENGINES;");
	        if (!empty($query)) {
	            while ($engines = $query->fetch_array(MYSQLI_ASSOC)) {
					if (strtoupper($engines['Engine']) == 'ROCKSDB')
						return true;
				}
	        }
		}
		catch (Exception $e) { }
        return false;
    }

    /**
     * Get all config
     *
     * @return array
     */
    public function GetAllConfig() : array {
        $_CONFIG = array();
        $query = $this->db->query("SELECT cfg, val FROM config");
        if (!empty($query)) {
            while ($cfg = $query->fetch_array(MYSQLI_ASSOC))
                $_CONFIG[$cfg['cfg']] = trim($cfg['val']);
        }
        return $_CONFIG;
    }

    /**
     * Get config
     *
     * @param $key
     * @return string
     */
    public function GetConfig(string $key) : string {
        $currentConfig = $this->db->query("SELECT val FROM config WHERE cfg = '".$key."';")->fetch_assoc();
        if (!empty($currentConfig)) {
            return $currentConfig['val'];
        }
        return "";
    }

    /**
     * Save config on database
     *
     * @param string $key
     * @param string $value
     */
    public function SetConfig(string $key,string $value) : void {
        $currentConfig = $this->db->query("SELECT val FROM config WHERE cfg = '".$key."';")->fetch_assoc();
        if (empty($currentConfig)) {
            $this->db->query("INSERT INTO config (cfg,val) VALUES ('".$key."', '".$value."');");
        }
        else {
            $this->db->query("UPDATE config SET val='".$value."' WHERE cfg='".$key."'");
        }
    }
}

?>
