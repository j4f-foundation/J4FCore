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

class CLI {

	/**
     * Set a Title for Wallet CLI
     *
     */
	public static function Title() {
		Display::ClearScreen();
		Display::printCLI("%G%J4F CLI Wallet - Version 0.0.1");
		Display::_br();
	}

	/**
     * Show MainMenu Wallet CLI
     *
     */
	public static function MainMenu() {
		self::Title();

		Display::printCLI("Menu actions:");
		Display::printCLI("1. New Wallet");
		Display::printCLI("2. Get Balance");
		Display::printCLI("3. Send Transaction");
		Display::printCLI("4. Encrypt data");
		Display::_br();
		Display::printCLI("0. Exit");
		Display::_br();
		Display::printCLI("Select option: ");

		$input = self::ReadInput();
		switch ($input) {
			case '1':
				self::NewWallet();
			break;
			case '2':
				self::GetBalance();
			break;
			case '3':
				self::SendMoney();
			break;
			case '4':
				self::EncryptData();
			break;
			case '0':
				exit();
			break;
			default:
				self::MainMenu();
			break;
		}
	}

	/**
     * Show NewWallet Menu
     *
     */
	public static function NewWallet() {
		self::Title();

		Display::printCLI("Create new J4F Wallet");
		Display::printCLI("Write %Y%:b%W% to return to MainMenu");

		$pw1 = null;
		$pw2 = null;
		while ($pw2 == null && $pw1 == null) {
			if ($pw1 == null) {
				Display::printCLI("Write Password: ");
				$password1 = self::ReadInput();

				if ($password1 == ':b') {
					self::MainMenu();
					break;
				}
				else {
					if ($password1 == '') {
						Display::errorCLI("Password can't be empty");
						self::ReadInput();
						continue;
					}

					if (strlen($password1) < 6) {
						Display::errorCLI("Min password length is 6");
						self::ReadInput();
						continue;
					}

					//Save password1
					$pw1 = $password1;
				}
			}
			if ($pw2 == null) {
				Display::printCLI("Confirm Password: ");
				$password2 = self::ReadInput();

				if ($password2 == ':b') {
					break;
				}
				else {
					if ($password2 == '') {
						Display::errorCLI("Password can't be empty");
						self::ReadInput();
						continue;
					}

					if (strlen($password2) < 6) {
						Display::errorCLI("Min password length is 6");
						self::ReadInput();
						continue;
					}

					if ($password1 != $password2) {
						Display::printCLI("Password not match");
						self::ReadInput();
						continue;
					}
				}

				//Save password2
				$pw2 = $password2;
			}
		}

		if ($pw1 == null || $pw2 == null) {
			self::MainMenu();
		}
		else {
			$wallet = Wallet::LoadOrCreate("",$pw2);
			if (is_array($wallet)) {
				$walletAddress = Wallet::GetWalletAddressFromPubKey($wallet["public"]);
				Display::printCLI("The wallet was generated correctly: %G%" . $walletAddress."%W%");
				Display::_br();
				Display::printCLI("Press any key to return to Main Menu");
				$input = self::ReadInput();
				self::MainMenu();
			}
			else {
				Display::errorCLI("An error occurred while trying to generate the Wallet");
				sleep(6);
				self::NewWallet();
			}
		}
	}

	/**
     * Show Balance Menu Wallet CLI
     *
     */
	public static function GetBalance() {
		self::Title();

		Display::printCLI("GetBalance of J4F Wallet");
		Display::printCLI("Write %Y%:b%W% to return to MainMenu");
		Display::_br();
		Display::printCLI("Wallet Address: ");
		$input = self::ReadInput();
		//J4Ff994e3b5c49c7f41eee4e3a5b0e6621f846b00ddd5142f7d66c888e1
		if (strlen($input) > 0) {
			if ($input == ":b") {
				self::MainMenu();
			}
			else {
				$balance = Wallet::GetBalance($input,true);
				if (is_numeric($balance)) {
					Display::printCLI("Balance: %G%" . $balance." %Y%J4F%W%");
					Display::_br();
					Display::printCLI("Press any key to return to Main Menu");
					$input = self::ReadInput();
					self::MainMenu();
				}
				else {
					Display::errorCLI($balance);
					$input = self::ReadInput();
					self::GetBalance();
				}
			}
		}
		else {
			self::GetBalance();
		}
	}

	/**
     * Show SendTransaction Menu Wallet CLI
     *
     */
	public static function SendMoney() {
		self::Title();

		Display::printCLI("Send J4F to other Wallet");
		Display::printCLI("Write %Y%:b%W% to return to MainMenu");
		Display::_br();

		$walletFrom = null;
		$walletFromPassword = null;
		$walletTo = null;
		$amount = null;
		$data = null;
		while ($walletFrom === null || $walletFromPassword === null || $walletTo === null || $amount === null || $data === null) {

			if ($walletFrom === null) {
				Display::printCLI("Write Wallet From (Use %Y%coinbase%W% to use local node wallet): ");
				$inputWalletFrom = self::ReadInput();
				if ($inputWalletFrom == ':b') {
					self::MainMenu();
					break;
				}
				else {
					if ($inputWalletFrom == '') {
						Display::errorCLI("Wallet from can't be null");
						self::ReadInput();
						continue;
					}

					if ($inputWalletFrom != 'coinbase') {
						$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
						if (!@preg_match($REGEX_Address,$inputWalletFrom)) {
							Display::errorCLI("Wallet From have bad format");
							self::ReadInput();
							continue;
						}
					}
					//Save WalletFrom
					$walletFrom = $inputWalletFrom;
				}
			}

			if ($walletTo === null) {
				Display::printCLI("Write Wallet To: ");
				$inputWalletTo = self::ReadInput();
				if ($inputWalletTo == ':b') {
					self::MainMenu();
					break;
				}
				else {
					if ($inputWalletTo == '') {
						Display::errorCLI("Wallet to can't be null");
						self::ReadInput();
						continue;
					}
					else {
						$REGEX_Address = '/J4F[a-fA-F0-9]{56}/';
						if (!@preg_match($REGEX_Address,$inputWalletTo)) {
							Display::errorCLI("Wallet From have bad format");
							self::ReadInput();
							continue;
						}

						//Save WalletFrom
						$walletTo = $inputWalletTo;
					}
				}
			}

			if ($walletFromPassword === null) {
				Display::printCLI("Write Wallet From Password: ");
				$inputWalletFromPassword = self::ReadInput();
				if ($inputWalletFromPassword == ':b') {
					self::MainMenu();
					break;
				}
				else {
					if ($walletFrom == 'coinbase') {
						//Save walletFromPassword COINBASE
						$walletFromPassword = '';
					}
					else {
						if ($inputWalletFromPassword == '') {
							Display::errorCLI("Wallet password can't be null");
							self::ReadInput();
							continue;
						}

						if (@strlen($inputWalletFromPassword) < 6) {
							Display::errorCLI("Password min length  is 6");
							self::ReadInput();
							continue;
						}
						//Save walletFromPassword
						$walletFromPassword = $inputWalletFromPassword;
					}
				}
			}

			if ($amount === null) {
				Display::printCLI("Write Amount to send: ");
				$inputAmount = self::ReadInput();
				if ($inputAmount == ':b') {
					self::MainMenu();
					break;
				}
				else {
					if ($inputAmount == null || $inputAmount == '') {
						Display::errorCLI("Amount can't be null");
						self::ReadInput();
						continue;
					}

					if ($inputAmount < 0) {
						Display::errorCLI("Can't send negative amount");
						self::ReadInput();
						continue;
					}

					$balance = Wallet::GetBalance($walletFrom,true);
					if (@bccomp($balance,$inputAmount) == -1) {
						Display::errorCLI("Not enought balance to send");
						self::ReadInput();
						continue;
					}

					//Save amount
					$amount = $inputAmount;
				}
			}

			if ($data === null) {
				Display::printCLI("Write data to send (Default empty): ");
				$inputData = self::ReadInput();
				if ($inputData == ':b') {
					self::MainMenu();
					break;
				}
				else {
					if ($inputData == null)
						$inputData = '';

					//Check walletFrom Format
					if (strlen($inputData) > 0) {
						$REGEX_Data = '/0x[a-fA-F0-9]{1,}/';
						if (!@preg_match($REGEX_Data,$inputData)) {
		                	Display::errorCLI("Data have bad format");
							self::ReadInput();
							continue;
						}
					}

					//Save data
					$data = $inputData;
				}
			}
		}

		Display::printCLI("Creating transaction, Please wait...");
		$txnHash = Wallet::SendTransaction($walletFrom,$walletFromPassword,$walletTo,$amount,$data);
		//Check if transaction have error
		if (strpos($txnHash,'Error') !== false) {
			Display::errorCLI($txnHash);
			Display::_br();
			Display::printCLI("Press any key to return to Main Menu");
			$input = self::ReadInput();
			self::MainMenu();
		} else {
			Display::printCLI($txnHash);
			Display::_br();
			Display::printCLI("Press any key to return to Main Menu");
			$input = self::ReadInput();
			self::MainMenu();
		}
	}

	/**
     * Show Balance Menu Wallet CLI
     *
     */
	public static function EncryptData() {
		self::Title();

		Display::printCLI("Encrypt data");
		Display::printCLI("Write %Y%:b%W% to return to MainMenu");
		Display::_br();
		Display::printCLI("Message to encrypt: ");
		$inputData = self::ReadInput();
		//J4Ff994e3b5c49c7f41eee4e3a5b0e6621f846b00ddd5142f7d66c888e1
		if (strlen($inputData) > 0) {
			if ($inputData == ":b") {
				self::MainMenu();
			}
			else {
				$dataParsed = Tools::str2hex($inputData);
				Display::printCLI('Message crypted:');
				Display::printCLI('%G%'.$dataParsed.'%W%');
				Display::_br();
				Display::printCLI("Press any key to return to Main Menu");
				self::ReadInput();
				self::MainMenu();
			}
		}
		else {
			self::EncryptData();
		}
	}

	/**
     * Read Input data from CLI
     *
     */
	public static function ReadInput() {
		$handle = fopen ("php://stdin","r");
		$line = fgets($handle);
		return trim($line);
	}
}
?>
