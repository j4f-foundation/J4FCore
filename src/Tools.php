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

class Tools {

	public static function str2hex($string) {
		if (strlen($string) == 0)
			return "0x";
		return '0x'.@bin2hex(@gzcompress(trim($string),9));
	}

	public static function hex2str($bytesHex) {
		if ($bytesHex == "0x")
			return "";
		$bytesHex = @str_replace('0x','',$bytesHex);
		return @trim(@gzuncompress(@hex2bin($bytesHex)));
	}

    /**
     * Transform a decimal number to hexadecimal
     * Using the php-bcmath package
     *
     * @param $number
     * @return mixed
     */
    public static function dec2hex($number)
    {
        $hexvalues = array('0','1','2','3','4','5','6','7',
            '8','9','A','B','C','D','E','F');
        $hexval = '';
        while($number != '0')
        {
            $hexval = $hexvalues[bcmod($number,'16')].$hexval;
            $number = bcdiv($number,'16',0);
        }
        return Tools::zeropad($hexval,60);
    }

    /**
     * Transform a hexadecimal number to a decimal
     * Using the php-bcmath package
     *
     * @param $number
     * @return mixed
     */
    public static function hex2dec($number)
    {
        $decvalues = array(
			'0' => '0', '1' => '1', '2' => '2',
            '3' => '3', '4' => '4', '5' => '5',
            '6' => '6', '7' => '7', '8' => '8',
            '9' => '9', 'A' => '10', 'B' => '11',
            'C' => '12', 'D' => '13', 'E' => '14',
            'F' => '15', 'a' => '10', 'b' => '11',
            'c' => '12', 'd' => '13', 'e' => '14',
            'f' => '15');
        $decval = '0';
        $number = @strrev($number);
        for($i = 0; $i < @strlen($number); $i++)
        {
            $decval = @bcadd(@bcmul(@bcpow('16',$i,0),$decvalues[$number{$i}]), $decval);
        }
        return $decval;
    }

    /**
     * Add zeros in front of a chain
     *
     * @param $num
     * @param $lim
     * @return mixed
     */
    public static function zeropad($num, $lim)
    {
        return (strlen($num) >= $lim) ? $num : self::zeropad("0" . $num, $lim);
    }

    /**
     * Transforms a serialized object into an instantiated object
     *
     * @param $instance
     * @param $className
     * @return mixed
     */
    public static function objectToObject($instance, $className) {
        return @unserialize(sprintf(
            'O:%d:"%s"%s',
            strlen($className),
            $className,
            strstr(strstr(serialize($instance), '"'), ':')
        ));
    }

    /**
     * Get ID from IP and PORT
     *
     * @param $ip
     * @param $port
     * @return bool|string
     */
    public static function GetIdFromIpAndPort($ip,$port) {
        return substr(PoW::hash($ip.$port),0,18);
    }

    /**
     * Write file with content
     * If file exist,delete
     *
     * @param $file
     * @param $content
     * @param $checkIfExistAndDelete
     */
    public static function writeFile($file,$content='',$checkIfExistAndDelete = false) {

        if ($checkIfExistAndDelete && @file_exists($file))
            @unlink($file);

        $fp = @fopen($file, 'w');
        @fwrite($fp, $content);
        @fclose($fp);
        @chmod($file, 0777);
    }

    /**
     * Write file with content
     * If file exist,delete
     *
     * @param $file
     * @param $content
     * @param $checkIfExistAndDelete
     */
    public static function writeLog($content='',$checkIfExistAndDelete = false) {

        $file = self::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.'node_log';

        if ($checkIfExistAndDelete && file_exists($file))
            unlink($file);

        $fp = fopen($file, 'a');
        fwrite($fp, $content.PHP_EOL);
        fclose($fp);
        @chmod($file, 0777);
    }

    /**
     * Send message to discord using webhook
     *
     * @param $numBlock
     * @param Block $blockMinedByPeer
     */
    public static function SendMessageToDiscord($numBlock,$blockMinedByPeer) {
        if (defined('WEBHOOK_DISCORD')) {
            $msg = "Block Found        height=**".$numBlock."**        nonce=**".$blockMinedByPeer->nonce."**        previous=**$blockMinedByPeer->previous**    hash=**$blockMinedByPeer->hash**";
            $ch = curl_init(WEBHOOK_DISCORD);
            curl_setopt( $ch, CURLOPT_POST, 1);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode(array('content'=>"$msg")));
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    /**
     * Clear TMP folder
     */
    public static function clearTmpFolder() {
        @unlink(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_MINERS_STARTED);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_TX_INFO);
        @unlink(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_NEW_BLOCK);
    }

    /**
     * @param DB $chaindata
     * @param $blockMined
     */
    public static function sendBlockMinedToNetwork(&$chaindata,$blockMined) {
        $peers = $chaindata->GetAllPeers();
        foreach ($peers as $peer) {
            $infoToSend = array(
                'action'            => 'MINEDBLOCK',
                'hash_previous'     => $blockMined->previous,
                'block'             => @serialize($blockMined)
            );

            Socket::sendMessage($peer['ip'],$peer['port'],$infoToSend);
        }
    }

    /**
     * Get block size
     *
     * @param array|object $block
     * @return string
     */
    public static function GetBlockSize($block) {
        if (is_array($block) || is_object($block))
            return self::GetSizeFormatted(strlen(@serialize($block)));
        return "0 b";
    }

    /**
     * Get size block formatted
     *
     * @param $bytes
     * @return string
     */
    public static function GetSizeFormatted($bytes) {

        if ($bytes > 1000000000000000000000000)
            $bytesFormatted = number_format($bytes / 1000000000000000000000000,2)." Yb";
        else if ($bytes > 1000000000000000000000)
            $bytesFormatted = number_format($bytes / 1000000000000000000000,2)." Zb";
        else if ($bytes > 1000000000000000000)
            $bytesFormatted = number_format($bytes / 1000000000000000000,2)." Eb";
        else if ($bytes > 1000000000000000)
            $bytesFormatted = number_format($bytes / 1000000000000000,2)." Pb";
        else if ($bytes > 1000000000000)
            $bytesFormatted = number_format($bytes / 1000000000000,2)." Tb";
        else if ($bytes > 1000000000)
            $bytesFormatted = number_format($bytes / 1000000000,2)." Gb";
        else if ($bytes > 1000000)
            $bytesFormatted = number_format($bytes / 1000000,2)." Mb";
        else if ($bytes > 1000)
            $bytesFormatted = number_format($bytes / 1000,2)." Kb";
        else
            $bytesFormatted = number_format($bytes,2)." b";

        return $bytesFormatted;
    }

    /**
     * @param DB $chaindata
     * @param Block $blockMined
     */
    public static function sendBlockMinedToNetworkWithSubprocess(&$chaindata,$blockMined,$skipPeer=null) {

        //Write block cache for propagation subprocess
        Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR.Subprocess::$FILE_PROPAGATE_BLOCK,Tools::str2hex(@serialize($blockMined)));

        if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= 3) {
            $mini_hash = substr($blockMined->hash,-12);
            $mini_hash_previous = substr($blockMined->previous,-12);

            Display::_debug("sendBlockMinedToNetworkWithSubprocess  %G%previous%W%=".$mini_hash_previous."  %G%hash%W%=".$mini_hash,3);
        }

        //Run subprocess propagation per peer
        $peers = $chaindata->GetAllPeers();
        $id = 0;
        foreach ($peers as $peer) {

			// If have skipPeer, check this
			if ($skipPeer != null)
				if ($skipPeer['ip'] == $peer['ip'] && $skipPeer['port'] == $skipPeer['port'])
					continue;

            //Params for subprocess
            $params = array(
                $peer['ip'],
                $peer['port'],
                1
            );

            //Run subprocess propagation
            Subprocess::newProcess(Tools::GetBaseDir()."subprocess".DIRECTORY_SEPARATOR,'propagate',$params,$id);

            $id++;
        }
    }

    /**
     * We create the base directories (if they did not exist)
     */
    public static function MakeDataDirectory() {

        Display::print("Data directory: %G%".Tools::GetBaseDir()."data".DIRECTORY_SEPARATOR);

        if (!@file_exists(Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets"))
            @mkdir(Tools::GetBaseDir().DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."wallets",755, true);

        if (!@file_exists(Tools::GetBaseDir().DIRECTORY_SEPARATOR."tmp"))
            @mkdir(Tools::GetBaseDir().DIRECTORY_SEPARATOR."tmp",755, true);
    }

    /**
     * Get base directory
     *
     * @return mixed|string
     */
    public static function GetBaseDir() {
        $dir = __DIR__;
        $dir = preg_replace('/src$/','',$dir);
        $dir = preg_replace('/data$/','',$dir);
        $dir = preg_replace('/cli$/','',$dir);
        $dir = preg_replace('/bin$/','',$dir);
        $dir = preg_replace('/subprocess$/','',$dir);
        return $dir;
    }

    /**
     * Get datetime diff
     *
     * @param $dt1
     * @param $dt2
     * @return stdClass
     */
    public static function datetimeDiff($dt1, $dt2){
        $t1 = strtotime($dt1);
        $t2 = strtotime($dt2);

        $dtd = new stdClass();
        $dtd->interval = $t2 - $t1;
        $dtd->total_sec = abs($t2-$t1);
        $dtd->total_min = floor($dtd->total_sec/60);
        $dtd->total_hour = floor($dtd->total_min/60);
        $dtd->total_day = floor($dtd->total_hour/24);

        $dtd->day = $dtd->total_day;
        $dtd->hour = $dtd->total_hour -($dtd->total_day*24);
        $dtd->min = $dtd->total_min -($dtd->total_hour*60);
        $dtd->sec = $dtd->total_sec -($dtd->total_min*60);
        return $dtd;
    }

	/**
	 * Get age date
	 *
	 * @param $ageObject
	 * @return string
	 */
	public static function getAge($ageObject) {
		$ageBlockMessage = "";

		if ($ageObject->day > 1)
			$ageBlockMessage .= $ageObject->day." days ";
		elseif ($ageObject->day > 0)
				$ageBlockMessage .= $ageObject->day." day ";

		if ($ageObject->hour > 0)
			$ageBlockMessage .= $ageObject->hour." hrs ";

		if ($ageObject->min > 0) {
			if (strlen($ageBlockMessage) > 0)
				$ageBlockMessage .= " ";

			$ageBlockMessage .= $ageObject->min." mins ";
		}

		if ($ageObject->sec > 0) {
			if (strlen($ageBlockMessage) > 0)
				$ageBlockMessage .= " ";

			$ageBlockMessage .= $ageObject->sec." secs";
		}
		return $ageBlockMessage;
	}

    /**
     * Get global time using HTTP Api
     * If no answer is obtained, local time is used
     *
     * @return int
     */
    public static function GetGlobalTimeByHTTPOrLocalTime() {
        $worldTime = @json_decode(@file_get_contents('http://worldtimeapi.org/api/timezone/Etc/UTC'));
        if (is_object($worldTime) && !empty($worldTime)) {
            return strtotime(date('Y-m-d H:i:s', $worldTime->unixtime));
        } else {
            return time();
        }
    }

	/**
     * Get local UNIX militime
     *
     * @return false|int
     */
	public static function GetGlobalMilitime() {
		return round(microtime(true) * 1000);
	}

    /**
     * Get local UNIX time
     *
     * @return false|int
     */
    public static function GetGlobalTime() {
        return time();
    }

    /**
     * Get global UNIX time using NTP Protocol
     *
     * In case can not get time from the NTP servers
     * will call GetGlobalTimeByHTTPOrLocalTime function
     *
     * @return false|int
     */
    public static function GetGlobalTimeByNTP() {
        $bit_max = 4294967296;
        $epoch_convert = 2208988800;
        $vn = 3;

        $servers = array('pool.ntp.org','0.pool.ntp.org','1.pool.ntp.org','2.pool.ntp.org','3.pool.ntp.org');
        $server_count = count($servers);

        //first byte
        //LI (leap indicator), a 2-bit integer. 00 for 'no warning'
        $header = '00';

        //VN (version number), a 3-bit integer.  011 for version 3
        $header .= sprintf('%03d',decbin($vn));

        //Mode (association mode), a 3-bit integer. 011 for 'client'
        $header .= '011';

        //construct the packet header, byte 1
        $request_packet = chr(bindec($header));

        //Define default empty response
        $response = '';
        $local_received = -1;
        $local_sent = -1;

        //we'll use a for loop to try additional servers should one fail to respond
        for($i=0; $i < $server_count; $i++) {
            $socket = @fsockopen('udp://'.$servers[$i], 123, $err_no, $err_str,1);

            if (!$socket) {
                //this was the last server on the list, so give up
                if ($i == $server_count-1)
                    return Tools::GetGlobalTimeByHTTPOrLocalTime();
            }

            else if ($socket) {
                //add nulls to position 11 (the transmit timestamp, later to be returned as originate)
                //10 lots of 32 bits
                for ($j=1; $j<40; $j++)
                    $request_packet .= chr(0x0);

                //the time our packet is sent from our server (returns a string in the form 'msec sec')
                $local_sent_explode = explode(' ',microtime());
                $local_sent = $local_sent_explode[1] + $local_sent_explode[0];

                //add 70 years to convert unix to ntp epoch
                $originate_seconds = $local_sent_explode[1] + $epoch_convert;

                //convert the float given by microtime to a fraction of 32 bits
                $originate_fractional = round($local_sent_explode[0] * $bit_max);

                //pad fractional seconds to 32-bit length
                $originate_fractional = sprintf('%010d',$originate_fractional);

                //pack to big endian binary string
                $packed_seconds = pack('N', $originate_seconds);
                $packed_fractional = pack("N", $originate_fractional);

                //add the packed transmit timestamp
                $request_packet .= $packed_seconds;
                $request_packet .= $packed_fractional;

                $response = '';
                if (fwrite($socket, $request_packet)) {
                    $data = NULL;
                    stream_set_timeout($socket, 1);

                    $response = fread($socket, 48);

                    //the time the response was received
                    $local_received = microtime(true);

                    //echo 'response was: '.strlen($response).$response;
                }
                fclose($socket);

                //the response was of the right length, assume it's valid and break out of the loop
                if (strlen($response) == 48)
                    break;
                else
                    //this was the last server on the list, so give up
                    if ($i == $server_count-1)
                        return Tools::GetGlobalTimeByHTTPOrLocalTime();
            }
        }

        //unpack the response to unsiged lonng for calculations
        $unpack0 = unpack("N12", $response);

        //present as a decimal number
        $remote_received_seconds = sprintf('%u', $unpack0[9])-$epoch_convert;
        $remote_transmitted_seconds = sprintf('%u', $unpack0[11])-$epoch_convert;

        $remote_received_fraction = sprintf('%u', $unpack0[10]) / $bit_max;
        $remote_transmitted_fraction = sprintf('%u', $unpack0[12]) / $bit_max;

        $remote_received = $remote_received_seconds + $remote_received_fraction;
        $remote_transmitted = $remote_transmitted_seconds + $remote_transmitted_fraction;

        //calculations assume a symmetrical delay, fixed point would give more accuracy
        $delay = (($local_received - $local_sent) / 2)  - ($remote_transmitted - $remote_received);

        $ntp_time =  $remote_transmitted - $delay;
        $ntp_time_explode = explode('.',$ntp_time);

        return $ntp_time_explode[0];
    }

	public static function numberFormat($number) {
		$tmp = explode('.', $number);
		$length = 0;
		if(count($tmp)>1) {
			$length = strlen($tmp[1]);
		}
		return number_format($number, $length);
	}
}
?>
