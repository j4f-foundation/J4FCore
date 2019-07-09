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

class Display {

    /**
     * Clean the screen
     */
    public static function ClearScreen() {
        echo "\033[2J";
    }

    /**
     * Replace the colors of a string for the CMD
     *
     * @param $string
     * @return mixed
     */
    public static function _replaceColors($string) {
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

    /**
     * Write a line in the CMD
     * @param $string
     */
    public static function _printer($string) {
        $date = new DateTime();
        $formatted_string = "%G%INFO%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%".PHP_EOL;
        $colored_string = self::_replaceColors($formatted_string);
        echo $colored_string;
        ob_flush();
    }

    /**
     * Write a debug line in the CMD
     * @param $string
     */
    public static function _debug($string) {
        $date = new DateTime();
        $formatted_string = "%Y%DEBUG%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%".PHP_EOL;
        $colored_string = self::_replaceColors($formatted_string);
        echo $colored_string;
        ob_flush();
    }

    /**
     * Write a Error line in the CMD
     * @param $string
     */
    public static function _error($string) {
        $date = new DateTime();
        $formatted_string = "%LR%ERROR%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%".PHP_EOL;
        $colored_string = self::_replaceColors($formatted_string);
        echo $colored_string;
        ob_flush();
    }

    /**
     * Write a Warning line in the CMD
     * @param $string
     */
    public static function _warning($string) {
        $date = new DateTime();
        $formatted_string = "%LR%WARN%W% [".$date->format("m-d|H:i:s")."] ".$string."%W%".PHP_EOL;
        $colored_string = self::_replaceColors($formatted_string);
        echo $colored_string;
        ob_flush();
    }

    /**
     * Line break
     */
    public static function _br() {
        echo PHP_EOL;
    }

    /**
     * Write a message of the mined block
     * @param Block $blockMined
     */
    public static function NewBlockMined($blockMined) {

        $mini_hash = substr($blockMined->hash,-12);
        $mini_hash_previous = substr($blockMined->previous,-12);

        //Obtenemos la diferencia entre la creacion del bloque y la finalizacion del minado
        $minedTime = date_diff(
            date_create(date('Y-m-d H:i:s', $blockMined->timestamp)),
            date_create(date('Y-m-d H:i:s', $blockMined->timestamp_end))
        );
        $blockMinedInSeconds = $minedTime->format('%im%ss');

        self::_printer("%Y%Mined%W% new block     		%G%nonce%W%=" . $blockMined->nonce . " %G%elapsed%W%=" . $blockMinedInSeconds . " %G%previous%W%=" . $mini_hash_previous . " %G%hash%W%=" . $mini_hash);
    }

    /**
     * Write a canceled block message
     * @param $gossip
     */
    public static function NewBlockCancelled() {
		if (SHOW_INFO_SUBPROCESS)
        	self::_printer("%Y%Miner work cancelled, another miner found block before");
    }
}
?>