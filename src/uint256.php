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

Class uint256 {

	/**
     * Transform a float number to uint256
     *
     * @param $number
     * @return string
     */
	public static function parse($number) {
		preg_match_all('/^(\d+\.\d*?[0-9])0{0,}$/',$number,$matches);
		if (!isset($matches[1][0]))
			return $number;
		$numParsed = $matches[1][0];
		if ($numParsed == '0.0')
			return '0';

		if (preg_match("/\.\d$/",$numParsed))
			$numParsed = str_replace('.0','',$numParsed);

		return $numParsed;
	}

	/**
     * Transform a uint256 to float
     *
     * @param $number
     * @return string
     */
	public static function toDec($number) {
		$convert = self::float(@bcdiv((string) $number, '1000000000000',12));
		return $convert;
	}

	/**
     * Add two uint256 numbers
     *
     * @param $number1
	 * @param $number2
     * @return string
     */
	public static function add($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;

		$result = @bcadd($num1,$num2,18);
		return self::parse($result);
	}

	/**
     * Subtract two uint256 numbers
     *
     * @param $number1
	 * @param $number2
     * @return string
     */
	public static function sub($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		$result = @bcsub($num1,$num2,18);
		return self::parse($result);
	}

	/**
     * Multiplies two uint256 numbers
     *
     * @param $number1
	 * @param $number2
     * @return string
     */
	public static function mul($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		$result = @bcmul($num1,$num2,18);
		return self::parse($result);
	}

	/**
     * Divide two uint256 numbers
     *
     * @param $number1
	 * @param $number2
     * @return string
     */
	public static function div($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		$result = @bcdiv($num1,$num2,18);
		return self::parse($result);
	}

	/**
     * Get modulus of two uint256 numbers
     *
     * @param $number1
	 * @param $number2
     * @return string
     */
	public static function mod($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		$result = @bcmod($num1, $num2,18);
		return self::parse($result);
	}

	/**
     * Raise two uint256 numbers
     *
     * @param $number1
	 * @param $number2
     * @return string
     */
	public static function pow($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		$result = @bcpow($num1, $num2,18);
		return self::parse($result);
	}

	/**
     * Get the square root of uint256
     *
     * @param $number
     * @return string
     */
	public static function sqrt($number) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$result = @bcsqrt($number,18);
		return self::parse($result);
	}

	/**
     * Raise two uint256 numbers, reduced by a specified modulus
     *
     * @param $number1
	 * @param $number2
	 * @param $modulus
     * @return string
     */
	public static function powmod($number1,$number2,$modulus) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		$result = @bcpowmod($num1, $num2,$modulus,18);
		return self::parse($result);
	}

	/**
     * Compare two uint256
     * Returns 0 if the two operands are equal, 1 if the left_operand is larger than the right_operand, -1 otherwise.
	 *
     * @param $number1
	 * @param $number2
     * @return int
     */
	public static function comp($number1,$number2) {
		$num1 = (strlen($number1) >= 78) ? self::toDec($number1):$number1;
		$num2 = (strlen($number2) >= 78) ? self::toDec($number2):$number2;
		return bccomp($num1,$num2,18);
	}

	/**
     * Parse float num
     *
     * @param $number
     * @return string
     */
	private static function float($num) {
		$matches = array();
		preg_match_all("/\.([1-9].*|(?:0{0,})[1-9].*|)/",$num,$matches);
		if (strlen($matches[1][0]) > 1) {
			$e_num = explode('.',$num);
			$convert = $e_num[0].'.'.$e_num[1];
		}
		else {
			$e_num = explode('.',$num);
			$convert = $e_num[0];
		}
		return $convert;
	}
}
?>
