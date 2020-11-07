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

class DB extends DBBase {

    public $db;

    /**
     * DB constructor.
     */
    public function __construct() {

        //We create or load the database
        $this->db = @new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME, DB_PORT);
		if (isset($this->db->connect_error) && strlen($this->db->connect_error) > 0) {
			Display::_error('Database ERROR');
			Display::_error($this->db->connect_error);
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				Display::_error("Press Enter to close close window");
				readline();
			}
		}
    }

    /**
     * Get current network
     *
     * @return string
     */
    public function GetNetwork() : string {
        $currentNetwork = $this->db->query("SELECT val FROM config WHERE cfg = 'network';")->fetch_assoc();
        if (!empty($currentNetwork))
            return strtolower($currentNetwork['val']);
        return "mainnet";
    }

    /**
     * @return array
     */
    public function GetBootstrapNode() : array {
        $info_mined_blocks_by_peer = $this->db->query("SELECT * FROM peers ORDER BY id ASC LIMIT 1;")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return null;
    }

    /**
     * Add a peer to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPeer(string $ip,string $port) : bool {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (empty($info_mined_blocks_by_peer)) {
            $this->db->query("INSERT INTO peers (ip,port) VALUES ('".$ip."', '".$port."');");
            return true;
        }
        return false;
    }

    /**
     * Returns whether or not we have this peer saved in the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function haveThisPeer(string $ip,string $port) : bool {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return true;
        }
        return false;
    }

    /**
     * Save config on database
     *
     * @param $ipAndPort
     */
    public function addPeerToBlackList(string $ipAndPort) : void {
        //Get IP and Port
        $tmp = explode(':',$ipAndPort);
        $ip = $tmp[0];
        $port = $tmp[1];

        if (strlen($ip) > 0 && strlen($port) > 0) {
            $currentInfoPeer = $this->db->query("SELECT id FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();

            //Ban peer 10min
            $blackListTime = time() + (5 * 60);
            if (empty($currentInfoPeer)) {
                $this->db->query("INSERT INTO peers (ip,port,blacklist) VALUES ('".$ip."', '".$port."', '".$blackListTime."');");
            }
            else {
                $this->db->query("UPDATE peers SET blacklist='".$blackListTime."' WHERE ip = '".$ip."' AND port = '".$port."';");
            }
        }
    }

    /**
     * Remove a peer from the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePeer(string $ip,string $port) : bool {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            if ($this->db->query("DELETE FROM peers WHERE ip = '".$ip."' AND port= '".$port."';"))
                return true;
        }
        return false;
    }

    /**
     * Returns an array with all the peers
     *
     * @return array
     */
    public function GetAllPeers() : array {
        $peers = [];
        $peers_chaindata = $this->db->query("SELECT * FROM peers WHERE blacklist IS NULL OR blacklist < ".time()." ORDER BY id");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $ip = str_replace("\r","",$peer['ip']);
                $ip = str_replace("\n","",$ip);

                $port = str_replace("\r","",$peer['port']);
                $port = str_replace("\n","",$port);

                $infoPeer = array(
                    'ip' => $ip,
                    'port' => $port
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

	/**
     * Returns true if this peer has blacklisted
	 *
	 * @param string $ip
	 * @param string $port
     * @return bool
     */
	public function CheckIfPeerIsBlacklisted(string $ip, string $port) : bool {
        $peers = [];
        $peersBlackListed = $this->db->query("SELECT * FROM peers WHERE ip = '".$ip."' AND port = '".$port."' AND blacklist IS NOT NULL AND blacklist >= ".time()." LIMIT 1")->fetch_array(MYSQLI_ASSOC);
        if (empty($peersBlackListed)) {
			return false;
        }
        return true;
    }

	/**
     * Returns an array with all the peers without bootstrap node peer
     *
     * @return array
     */
    public function GetAllPeersWithoutBootstrap() : array {
        $peers = [];
        $peers_chaindata = $this->db->query("SELECT * FROM peers WHERE blacklist IS NULL OR blacklist < ".time()." ORDER BY id");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
				if ($peer['ip'] == NODE_BOOTSTRAP || $peer['ip'] == NODE_BOOTSTRAP_TESTNET)
					continue;

                $infoPeer = array(
                    'ip' => $peer['ip'],
                    'port' => $peer['port']
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

    /**
     * Returns an array with 25 random peers
     *
     * @return array
     */
    public function GetPeers() : array {
        $peers = [];
        $peers_chaindata = $this->db->query("SELECT * FROM peers WHERE blacklist IS NULL OR blacklist < ".time()." LIMIT 25");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $infoPeer = array(
                    'ip' => $peer['ip'],
                    'port' => $peer['port']
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

	/**
     * Returns the information of a wallet
     *
     * @param string $wallet
     * @return array
     */
    public function GetWalletInfo(string $wallet) : array {

		$totalSpend = $totalReceivedReal = $current = $totalReceived = 0;

		$walletInfo = $this->db->query("SELECT * FROM accounts WHERE hash = '".$wallet."';")->fetch_assoc();
        if (!empty($walletInfo)) {
			$totalSpend = uint256::parse($walletInfo['sended']);
			$totalReceived = uint256::parse($walletInfo['received']);
			$totalMined = uint256::parse($walletInfo['mined']);

			$current = @bcadd($walletInfo['received'],$walletInfo['mined'],18);
			$current = @bcsub($current,$walletInfo['sended'],18);
			$current = @bcsub($current,$walletInfo['fees'],18);
			$current = uint256::parse($current);
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
	 * @param string $wallet
	 * @return array
	 */
	public function GetWalletTokens(string $wallet) : array {

		$tokens = ['j4frc10'=>[],'j4frc20'=>[]];

		$tokensAccount = $this->db->query("SELECT * FROM accounts_j4frc10 WHERE hash = '".$wallet."';");
		if (!empty($tokensAccount)) {
			while ($tokenAccountInfo = $tokensAccount->fetch_array(MYSQLI_ASSOC)) {
				$tokenHash = $tokenAccountInfo['contract_hash'];

				$totalSpend = uint256::parse($tokenAccountInfo['sended']);
				$totalReceivedReal = uint256::parse($tokenAccountInfo['received']);
				$current = uint256::parse(bcsub($tokenAccountInfo['received'],$tokenAccountInfo['sended'],18));


				$tokens['j4frc10'][$tokenHash]['info'] = array(
		            'sended' => $totalSpend,
		            'received' => $totalReceivedReal,
		            'current' => $current
		        );
			}
		}
		foreach ($tokens['j4frc10'] as $tokenHash=>$tokenInfo) {

			$contractInfo = $this->GetContractByHash($tokenHash);
			$tokenDefines = J4FVMTools::getTokenDefine(Tools::hex2str($contractInfo['code']));

			$tokens['j4frc10'][$tokenHash]['Token'] = trim($tokenDefines['Token']);
			$tokens['j4frc10'][$tokenHash]['Name'] = trim($tokenDefines['Name']);
		}

		$tokensRC20Account = $this->db->query("SELECT * FROM accounts_j4frc20 WHERE hash = '".$wallet."';");
		if (!empty($tokensRC20Account)) {
			while ($tokenRC20AccountInfo = $tokensRC20Account->fetch_array(MYSQLI_ASSOC)) {
				$tokenHash = $tokenRC20AccountInfo['contract_hash'];
				$tokenId = $tokenRC20AccountInfo['tokenId'];

				$tokens['j4frc20'][$tokenHash]['tokens'][$tokenId] = true;
			}
		}
		foreach ($tokens['j4frc20'] as $tokenHash=>$tokenInfo) {

			$contractInfo = $this->GetContractByHash($tokenHash);
			$tokenDefines = J4FVMTools::getTokenDefine(Tools::hex2str($contractInfo['code']));

			$tokens['j4frc20'][$tokenHash]['Token'] = trim($tokenDefines['Token']);
			$tokens['j4frc20'][$tokenHash]['Name'] = trim($tokenDefines['Name']);
		}

		return $tokens;
	}

}

?>
