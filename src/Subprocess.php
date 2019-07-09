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
			// Lanza el proceso en background
            @pclose(@popen('start /B cmd /C "'.PHP_RUN_COMMAND.' '.$directory.$fileProcess.'.php '.$params.' >NUL 2>NUL"', 'r'));
            //@pclose(@popen('start cmd /C "'.PHP_RUN_COMMAND.' '.$directory.$fileProcess.'.php '.$params.' >NUL 2>NUL"', 'r'));
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