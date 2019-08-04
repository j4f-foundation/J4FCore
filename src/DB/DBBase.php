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

class DBBase extends DBBlocks {

    /**
     * @param $table
     * @return bool
     */
    public function truncate($table) {
        if ($this->db->query("TRUNCATE TABLE " . $table.";"))
            return true;
        return false;
    }

    /**
     * Get all config
     *
     * @return array
     */
    public function GetAllConfig() {
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
    public function GetConfig($key) {
        $currentConfig = $this->db->query("SELECT val FROM config WHERE cfg = '".$key."';")->fetch_assoc();
        if (!empty($currentConfig)) {
            return $currentConfig['val'];
        }
        return null;
    }

    /**
     * Save config on database
     *
     * @param $key
     * @param $value
     */
    public function SetConfig($key,$value) {
        $currentConfig = $this->db->query("SELECT val FROM config WHERE cfg = '".$key."';")->fetch_assoc();
        if (empty($currentConfig)) {
            $this->db->query("INSERT INTO config (cfg,val) VALUES ('".$key."', '".$value."');");
        }
        else {
            $this->db->query("UPDATE config SET val='".$value."' WHERE cfg='".$key."'");
        }
    }

    /**
     * Get current network
     *
     * @return string
     */
    public function GetNetwork() {
        $currentNetwork = $this->db->query("SELECT val FROM config WHERE cfg = 'network';")->fetch_assoc();
        if (!empty($currentNetwork))
            return strtolower($currentNetwork['val']);
        return "mainnet";
    }

    /**
     * @return bool|mixed
     */
    public function GetBootstrapNode() {
        $info_mined_blocks_by_peer = $this->db->query("SELECT * FROM peers ORDER BY id ASC LIMIT 1;")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Returns the information of a wallet
     *
     * @param $wallet
     * @return array
     */
    public function GetWalletInfo($wallet) {

		$totalSpend = $totalReceivedReal = $current = $totalReceived = 0;

		$walletInfo = $this->db->query("SELECT * FROM accounts WHERE hash = '".$wallet."';")->fetch_assoc();
        if (!empty($walletInfo)) {
			$totalSpend = uint256::parse($walletInfo['sended']);
			$totalReceived = uint256::parse($walletInfo['received']);
			$totalMined = uint256::parse($walletInfo['mined']);
			$totalMinedAndReceived = @bcadd($walletInfo['received'],$walletInfo['mined'],18);
			$current = uint256::parse(@bcsub($totalMinedAndReceived,$walletInfo['sended'],18));
        }

		return array(
            'sended' => $totalSpend,
            'received' => $totalReceived,
			'mined' => $totalReceivedReal,
            'current' => $current
        );

    }

	/**
	 * Returns the tokens of a wallet
	 *
	 * @param $wallet
	 * @return array
	 */
	public function GetWalletTokens($wallet) {

		$tokens = array();

		$tokensAccount = $this->db->query("SELECT * FROM accounts_j4frc10 WHERE hash = '".$wallet."';");
		if (!empty($tokensAccount)) {
			while ($tokenAccountInfo = $tokensAccount->fetch_array(MYSQLI_ASSOC)) {
				$tokenHash = $tokenAccountInfo['contract_hash'];

				$totalSpend = uint256::parse($tokenAccountInfo['sended']);
				$totalReceivedReal = uint256::parse($tokenAccountInfo['received']);
				$current = uint256::parse(bcsub($tokenAccountInfo['received'],$tokenAccountInfo['sended'],18));


				$tokens[$tokenHash]['info'] = array(
		            'sended' => $totalSpend,
		            'received' => $totalReceivedReal,
		            'current' => $current
		        );
			}
		}

		foreach ($tokens as $tokenHash=>$tokenInfo) {

			$contractInfo = $this->GetContractByHash($tokenHash);
			$tokenDefines = J4FVM::getTokenDefine(Tools::hex2str($contractInfo['code']));

			$tokens[$tokenHash]['Token'] = trim($tokenDefines['Token']);
			$tokens[$tokenHash]['Name'] = trim($tokenDefines['Name']);
		}

		return $tokens;
	}
}

?>
