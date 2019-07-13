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

Class uint256 {

	/**
     * Transform a float number to uint256
     *
     * @param $number
     * @return string
     */
	public static function parse($number) {

		if (strlen($number) >= 78 && (strpos($number,'-') === false))
			return $number;

		if (strpos($number,'-') !== false)
			$number = 0;
			
		$convert = @bcmul((string) $number, '1000000000000');
		return @str_pad($convert, 78, "0", STR_PAD_LEFT);
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

		$result = @bcadd($num1,$num2,12);
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
		$result = @bcsub($num1,$num2,12);
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
		$result = @bcmul($num1,$num2,12);
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
		$result = @bcdiv($num1,$num2,12);
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
		$result = @bcmod($num1, $num2,12);
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
		$result = @bcpow($num1, $num2,12);
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
		$result = @bcsqrt($number,12);
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
		$result = @bcpowmod($num1, $num2,$modulus,12);
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
		return bccomp($num1,$num2,12);
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
