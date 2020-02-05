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

class PoW {
    /**
     * @param $message
     * @return string
     */
    public static function hash(string $message) : string {
		return hash('sha3-512', hash('sha3-256',hash('sha3-256', $message)));
    }

    /**
     * POW to find the hash that matches the current difficulty
     *
     * @param int $idMiner
     * @param string $message
     * @param string $difficulty
     * @param string $startNonce
     * @param string $incrementNonce
	 * @param bool $isMultiThread
     * @return string
     */
    public static function findNonce($idMiner,$message,$difficulty,$startNonce,$incrementNonce,$isMultiThread=true) : string {
        $max_difficulty = "000FFFFFF00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000";

		$nonce = "0";
        $nonce = bcadd($nonce,strval($startNonce));

        //Save current time
        $lastLogTime = time();

        //Can't start subprocess without mainthread
        if ($isMultiThread && !file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK))
            die('MAINTHREAD NOT FOUND');

		$countIdle = 0;
        $countIdleCheck = 0;
        $countIdleLog = 0;
        $limitCount = 1000;

        while(!self::isValidNonce($message,$nonce,$difficulty,$max_difficulty)) {

            $countIdle++;
            $countIdleLog++;
			$countIdleCheck++;

            if ($countIdleLog == $limitCount) {
                $countIdleLog = 0;

                //We obtain the difference between first 100000 hashes time and this hash time
                $minedTime = date_diff(
                    date_create(date('Y-m-d H:i:s', $lastLogTime)),
                    date_create(date('Y-m-d H:i:s', time()))
                );
                $timeCheckedHashesSeconds = intval($minedTime->format('%s'));
                $timeCheckedHashesMinutes = intval($minedTime->format('%i'));
                if ($timeCheckedHashesSeconds > 0)
                    $timeCheckedHashesSeconds = $timeCheckedHashesSeconds + ($timeCheckedHashesMinutes * 60);

                $currentLimitCount = $limitCount;
                if ($timeCheckedHashesSeconds <= 0) {
                    $timeCheckedHashesSeconds = 1;
                    $limitCount *= 10;
                }

                $hashRateMiner = $currentLimitCount / $timeCheckedHashesSeconds;

                //Save current time
                $lastLogTime = time();

				if ($isMultiThread)
                	Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$idMiner."_hashrate",$hashRateMiner);
                //Subprocess::writeLog("Miners has checked ".$nonce." - Current hash rate: " . $hashRateMiner);

            }

			if ($countIdleCheck == 100) {
				$countIdleCheck = 0;
                //Quit-Files
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING)) {
                    //Delete "pid" file
                    @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$startNonce);
                    die('STOP MINNING');
                }
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK)) {
                    //Delete "pid" file
                    @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$startNonce);
                    die('BLOCK FOUND');
                }
                if (!file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO)) {
                    //Delete "pid" file
                    @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$startNonce);
                    die('NO TX INFO');
                }
			}

            //Check alive status every 1000 hashes
            if ($countIdle % 1000 == 0 && $isMultiThread) {
                $countIdle = 0;

				//Update "pid" file every 1000 hashes
                Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_THREAD_CLOCK."_".$idMiner,time());

                //Check if MainThread is alive
                if (@file_exists(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK)) {
                    $mainThreadTime = @file_get_contents(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_MAIN_THREAD_CLOCK);
                    $minedTime = date_diff(
                        date_create(date('Y-m-d H:i:s', $mainThreadTime)),
                        date_create(date('Y-m-d H:i:s', time()))
                    );
                    $diffTime = $minedTime->format('%s');
                    if ($diffTime >= MINER_TIMEOUT_CLOSE)
                        die('MAINTHREAD NOT FOUND');
                }
            }

            //We increased the nonce to continue in the search to solve the problem
            $nonce = bcadd($nonce,strval($incrementNonce));
        }

        return $nonce;
    }

    /**
     * @param $message
     * @param $nonce
     * @param $difficulty
     * @param $maxDifficulty
     * @return bool
     */
    public static function isValidNonce(string $message,string $nonce,string $difficulty,string $maxDifficulty) : bool {

		$difficulty = ($difficulty < 0) ? 1:$difficulty;
		$hash = PoW::hash($message.$nonce);
        $targetHash = @bcdiv(Tools::hex2dec($maxDifficulty),$difficulty);
		$hashValue = Tools::hex2dec(strtoupper($hash));

        $result = bccomp($targetHash,$hashValue);
        if ($result === 1 || $result === 0)
            return true;
        return false;
    }
}
?>
