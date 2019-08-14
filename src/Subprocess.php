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

class Subprocess {

    public static $FILE_STOP_MINING         = "STOP_MINING";
    public static $FILE_MINERS_STARTED      = "MINERS_STARTED";
    public static $FILE_TX_INFO             = "TX_INFO";
    public static $FILE_PROPAGATE_BLOCK     = "PROPAGATE_BLOCK";
    public static $FILE_NEW_BLOCK           = "NEW_BLOCK";
    public static $FILE_MAIN_THREAD_CLOCK   = "main_thread_time";
    public static $FILE_MINERS_THREAD_CLOCK = "miners_thread_time";


    /**
     * Start new thread process
     *
     * @param string $directory
     * @param string $fileProcess
     * @param array|string $params
     * @param int $id
     */
    public static function newProcess($directory,$fileProcess,$params,$id=0) {
        if (is_array($params))
            $params = implode(" ",$params);

        //add id as first param
        $params = $id . " " . $params;

		if (IS_WIN)
            @pclose(@popen('start /B cmd /C "'.PHP_RUN_COMMAND.' '.$directory.$fileProcess.'.php '.$params.' >NUL 2>NUL"', 'r'));
        else
            system(PHP_RUN_COMMAND." ".$directory.$fileProcess.".php ".$params." > /dev/null 2>&1 &");

        if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 2)
            Display::_debug("%Y%Started new subprocess%W%   %G%process%W%=".$fileProcess."    %G%params%W%=".$params);
    }

    /**
     * Restart a miner thread
     *
     * @param $lastBlock
     * @param $directoryProcessFile
     * @param $isTestnet
     * @param $difficulty
     * @param int $id
     */
    public static function RestartMinerThread($lastBlock,$directoryProcessFile,$isTestnet,$difficulty,$id=0) {

        $network = "mainnet";
        if ($isTestnet)
            $network = "testnet";

        $params = array(
            $lastBlock['block_hash'],
            $difficulty,
            $id,
            MINER_MAX_SUBPROCESS,
            $network
        );
        self::newProcess($directoryProcessFile,'miner',$params,$id);
    }

}

?>
