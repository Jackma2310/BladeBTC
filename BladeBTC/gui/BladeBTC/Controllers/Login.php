<?php
namespace BladeBTC\GUI\Controllers;

use BladeBTC\GUI\Helpers\Password;
use BladeBTC\GUI\Helpers\Redirect;
use BladeBTC\GUI\Helpers\Request;
use BladeBTC\GUI\Helpers\Session;
use BladeBTC\GUI\Helpers\Utils;
use BladeBTC\GUI\Models\AccountModel;
use BladeBTC\GUI\Models\GroupModel;
use BladeBTC\GUI\Helpers\JWToken;
use Exception;

class Login
{

	public static function login()
	{

		/**
		 * Form value
		 */
		$username = Request::post('username');
		$password = Request::post('password');
		$remember = Request::post('remember');


		if (empty($username) || empty($password)) {
			throw new Exception("Vous devez entrer un nom d'utilisateur et un mot de passe.");
		}

		/**
		 * AccountModel ID
		 */
		$account_id = AccountModel::getIdByUsername($username);


		/**
		 * Validate AccountModel
		 */
		if (is_null($account_id)) {
			throw new Exception("Ce nom d'utilisateur n’est pas valide.");
		}

		/**
		 * Login attempt
		 */
		$login_attempt = AccountModel::getLoginAttempt($account_id);
		if ($login_attempt >= 5) {
			throw new Exception("Votre compte a été suspendu par mesure de sécurité.");
		}


		/**
		 * Validate password
		 */
		$pwd_hash = AccountModel::getPassword($account_id);
		if (!Password::isValid($password, $pwd_hash)) {

			AccountModel::setLoginAttempt($account_id, $login_attempt + 1);
			$remaining = 5 - AccountModel::getLoginAttempt($account_id);
			if ($remaining == 0) {
				throw new Exception("Votre compte a été suspendu par mesure de sécurité.");
			} else {
				throw new Exception("Nom d'utilisateur ou mot de passe incorrect. <br/>Vous disposez de $remaining essais avant que votre compte soit suspendu.");
			}
		}

		/**
		 * Rehash password as needed
		 */
		$rehash = Password::needsRehash($password, $pwd_hash);
		if ($rehash != false) {
			AccountModel::setPassword($account_id, $rehash);
		}

		/**
		 * Check if account is deleted
		 */
		$deleted = AccountModel::isDeleted($account_id);
		if ($deleted) {
			throw new Exception("Ce compte est invalide.");
		}

		/**
		 * Reset login attempt
		 */
		AccountModel::setLoginAttempt($account_id, 0);

		/**
		 * Set Session value
		 */
		Session::set("account_id", $account_id);
		Session::set("account_group", AccountModel::getAccountGroup($account_id));

		/**
		 * Update user account
		 */
		AccountModel::setLastIp($account_id, Utils::getIP());
		AccountModel::setLastLoginDate($account_id);

		/**
		 * Generate Cookie
		 */
		if (!empty($remember)) {
			$token = JWToken::encode($account_id, AccountModel::getFullName($account_id), 43200, ["grp" => AccountModel::getAccountGroup($account_id)]);
			setcookie("intra", $token, time() + 43200, "/");
		}

		/**
		 * Redirect to Dashboard
		 */
		self::redirect();
	}

	/**
	 * Redirect to dashboard
	 */
	public static function redirect()
	{
		$account_group = Session::get("account_group");
		$module = GroupModel::getDashboardById($account_group);
		Redirect::module($module);
	}


	/**
	 * Logout current user.
	 */
	public static function logout()
	{
		Session::kill();
		setcookie("intra", "", time() - 96400, "/");
		Redirect::to("index.php");
	}
}