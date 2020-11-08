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

class Display {

    /**
     * Clean the screen
     */
    public static function ClearScreen() : void {
        echo "\033[2J";
    }

    /**
     * Write a line in the CMD
     * @param $string
     */
    public static function print(string $string) : void {
		$date = new DateTime();
        echo self::_replaceColors("%G%INFO%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%").self::_br();
        ob_flush();
    }

	/**
     * Write a line in the CMD (CLI)
     * @param $string
     */
    public static function printCLI(string $string) : void {
    	echo self::_replaceColors($string."%W%").self::_br();
        ob_flush();
    }

    /**
     * Write a debug line in the CMD
     * @param $string
	 * @param $int
     */
    public static function _debug(string $string, int $level) : void {
		if (DISPLAY_DEBUG && DISPLAY_DEBUG_LEVEL >= $level) {
			$date = new DateTime();
			echo self::_replaceColors("%Y%DEBUG%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%").self::_br();
			ob_flush();
		}
    }

    /**
     * Write a Error line in the CMD
     * @param $string
     */
    public static function _error(string $string) : void {
        $date = new DateTime();
        echo self::_replaceColors("%LR%ERROR%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%").self::_br();
        ob_flush();
    }

	/**
     * Write a Error line in the CMD
     * @param $string
     */
    public static function errorCLI(string $string) : void {
        echo self::_replaceColors("%LR%ERROR%W% ".$string."%W%").self::_br();
        ob_flush();
    }

    /**
     * Write a Warning line in the CMD
     * @param $string
     */
    public static function _warning(string $string) : void {
        $date = new DateTime();
        echo self::_replaceColors("%LR%WARN%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%").self::_br();
        ob_flush();
    }

	public static function displayFromSubprocess() : void {
		$content = @file_get_contents(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display");
        $date = new DateTime();
        echo self::_replaceColors("%G%INFO%W% [".$date->format("m-d|H:i:s")."] ".$content."%W%").self::_br();
        ob_flush();
    }

    /**
     * Line break
     */
    public static function _br() {
        echo PHP_EOL;
    }

	/**
     * Display a message of new block
	 *
	 * @param string $type
	 * @param int $height
     * @param Block $blockMined
     */
    public static function ShowMessageNewBlock(string $type,int $height, Block $blockMined) : void {

        $mini_hash = substr($blockMined->hash,-12);
        $mini_hash_previous = substr($blockMined->previous,-12);

        //Obtenemos la diferencia entre la creacion del bloque y la finalizacion del minado
        $minedTime = date_diff(
            date_create(date('Y-m-d H:i:s', $blockMined->timestamp)),
            date_create(date('Y-m-d H:i:s', $blockMined->timestamp_end))
        );
        $blockMinedInSeconds = $minedTime->format('%im%ss');

		//Force Stop minning
		Tools::writeFile(Tools::GetBaseDir().'tmp'.DIRECTORY_SEPARATOR.Subprocess::$FILE_STOP_MINING);

		if (strtolower($type) == 'imported') {
	        self::print("%Y%Imported%W% new block	    	%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . ($height+1)." %G%size%W%=".Tools::GetBlockSize($blockMined));
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%Y%Imported%W% new block	    	%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . ($height+1)." %G%size%W%=".Tools::GetBlockSize($blockMined));
		}
		else if (strtolower($type) == 'sanity') {
	        self::print("%Y%Sanity%W% new block	     		%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . ($height)." %G%size%W%=".Tools::GetBlockSize($blockMined));
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%Y%Sanity%W% new block	     		%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . ($height)." %G%size%W%=".Tools::GetBlockSize($blockMined));
		}
		else if (strtolower($type) == 'mined') {
	        self::print("%Y%Mined%W% new block	     		%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . ($height)." %G%size%W%=".Tools::GetBlockSize($blockMined));
			//Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%Y%Mined%W% new block	     		%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash . " %G%number%W%=" . ($height)." %G%size%W%=".Tools::GetBlockSize($blockMined));
		}
		else if (strtolower($type) == 'rewardko') {
	        Display::print("%LR%Ignored%W% new block     	%G%error%W%=Reward Block not valid  			%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%LR%Ignored%W% new block     	%G%error%W%=Reward Block not valid  			%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
		}
		else if (strtolower($type) == 'novalid') {
	        Display::print("%LR%Ignored%W% new block     	%G%error%W%=Block not valid  					%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%LR%Ignored%W% new block     	%G%error%W%=Block not valid  					%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
		}
		else if (strtolower($type) == 'previousko') {
	        Display::print("%LR%Ignored%W% new block     	%G%error%W%=Previous block does not match  		%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%LR%Ignored%W% new block     	%G%error%W%=Previous block does not match  		%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
		}
		else if (strtolower($type) == 'noacepted') {
	        Display::print("%LR%Ignored%W% new block     	%G%error%W%=Block in same height not accepted  	%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%LR%Ignored%W% new block     	%G%error%W%=Previous block does not match  		%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
		}
		else if (strtolower($type) == 'diffko') {
	        Display::print("%LR%Ignored%W% new block     	%G%error%W%=Difficulty hacked?  	%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%LR%Ignored%W% new block     	%G%error%W%=Difficulty hacked?  	%G%previous%W%=" . $mini_hash_previous . "  %G%hash%W%=" . $mini_hash);
		}
		else if (strtolower($type) == 'malformed') {
	        Display::print("%LR%Ignored%W% new block     	%G%error%W%=Block malformed");
			Tools::writeFile(Tools::GetBaseDir()."tmp".DIRECTORY_SEPARATOR."display","%LR%Ignored%W% new block     	%G%error%W%=Block malformed");
		}
    }

	/**
     * Replace the colors of a string for the CMD
     *
     * @param $string
     * @return string
     */
    public static function _replaceColors($string) : string {
        $string = str_replace("%B%",ColorsCLI::$FG_BLACK,$string);
        $string = str_replace("%DG%",ColorsCLI::$FG_DARK_GRAY,$string);
        $string = str_replace("%R%",ColorsCLI::$FG_RED,$string);
        $string = str_replace("%LR%",ColorsCLI::$FG_LIGHT_RED,$string);
        $string = str_replace("%G%",ColorsCLI::$FG_GREEN,$string);
        $string = str_replace("%LG%",ColorsCLI::$FG_LIGHT_GREEN,$string);
        $string = str_replace("%BR%",ColorsCLI::$FG_BROWN,$string);
        $string = str_replace("%Y%",ColorsCLI::$FG_YELLOW,$string);
        $string = str_replace("%B%",ColorsCLI::$FG_BLUE,$string);
        $string = str_replace("%LB%",ColorsCLI::$FG_LIGHT_BLUE,$string);
        $string = str_replace("%P%",ColorsCLI::$FG_PURPLE,$string);
        $string = str_replace("%LP%",ColorsCLI::$FG_LIGHT_PURPLE,$string);
        $string = str_replace("%C%",ColorsCLI::$FG_CYAN,$string);
        $string = str_replace("%LC%",ColorsCLI::$FG_LIGHT_CYAN,$string);
        $string = str_replace("%LG%",ColorsCLI::$FG_LIGHT_GRAY,$string);
        $string = str_replace("%W%",ColorsCLI::$FG_WHITE,$string);
        return $string;
    }
}
?>
