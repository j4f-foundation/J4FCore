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

class ArgvParser
{
    const MAX_ARGV = 1000;
    /**
     * Parse arguments
     *
     * @param array|string [$message] input arguments
     * @return array Configs Key/Value
     */
    public function parseConfigs(&$message = null)
    {
        if (is_string($message)) {
            $argv = explode(' ', $message);
        } else if (is_array($message)) {
            $argv = $message;
        } else {
            global $argv;
            if (isset($argv) && count($argv) > 1) {
                array_shift($argv);
            }
        }
        $index = 0;
        $configs = array();
        while ($index < self::MAX_ARGV && isset($argv[$index])) {
            if (preg_match('/^([^-\=]+.*)$/', $argv[$index], $matches) === 1) {
                // not have ant -= prefix
                $configs[$matches[1]] = true;
            } else if (preg_match('/^-+(.+)$/', $argv[$index], $matches) === 1) {
                // match prefix - with next parameter
                if (preg_match('/^-+(.+)\=(.+)$/', $argv[$index], $subMatches) === 1) {
                    $configs[$subMatches[1]] = $subMatches[2];
                } else if (isset($argv[$index + 1]) && preg_match('/^[^-\=]+$/', $argv[$index + 1]) === 1) {
                    // have sub parameter
                    $configs[$matches[1]] = $argv[$index + 1];
                    $index++;
                } else {
                    $configs[$matches[1]] = true;
                }
            }
            $index++;
        }
        return $configs;
    }
}
?>