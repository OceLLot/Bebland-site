<?php

	# STORAGE #
	define('MYSQL_HOST', 'localhost');
	define('MYSQL_PORT', 3306);
	define('MYSQL_DATABASE', 'jba');
	define('MYSQL_USER', 'root');
	define('MYSQL_PASSWORD', 'root');

	# GOOGLE RE-CAPTCHA #
	
	# JPREMIUM OPTION #
	define('REAL_UNIQUE_IDS', true);

	# MESSAGES #
	define('NICKNAME_MISSING', 'Вы не ввели ник');
	define('PASSWORD_MISSING', 'Вы не ввели пароль');
	define('REPEAT_PASSWORD_MISSING', 'Вы не ввели повтор пароля');
	define('INVALID_NICKNAME', 'Неподдерживаемые символы. Используйте a-z, A-Z, 0-9, _');
	define('UNSAFE_PASSWORD', 'Слишком слабый пароль');
	define('DIFFERENT_PASSWORDS', 'Ваши пароли не совпадают');
	define('CRACKED_CLAIMED', 'На сервере уже есть пользователь с таким ником');
	define('CRACKED_REGISTER_ON_SERVER', 'Зарегистрируйте ваш аккаунт на сервере');
	define('PREMIUM_ClAIMED', 'Лицензионный пользователь уже использует этот ник');
	define('REGISTERED', 'Ваш аккаунт зарегистрирован! <br> Теперь вы можете зайти на <span>mc.bebland.net</span>');
	define('INTERNAL_ERROR', 'Ошибка 10 (Сообщите администратору) ');
	
	error_reporting(0);

	$error;

	try {
		if (empty($_POST['sent'])) {
			throw new Exception();
		}

		validateInputData();

		$nickname = $_POST['nickname'];
		$password = $_POST['password'];
		$repeatPassword = $_POST['repeat_password'];

		validateNicknameAndPassword($nickname, $password, $repeatPassword);

		$connection = openDatabaseConnection();
		isCrackedUserRegistered($connection, $nickname);
		isCrackedUserCanRegisterOnSerer($connection, $nickname);
		$premiumId = fetchPremiumId($nickname);
		isPremiumUserRegistered($connection, $nickname, $premiumId);
		isPremiumExists($nickname);

		registerNewUser($connection, $nickname, $password);

	} catch(Exception $exception) {
		$error = $exception->getMessage();
	}

	function validateInputData() {
		if (empty($_POST['nickname'])) {
			throw new Exception(NICKNAME_MISSING);
		}

		if (empty($_POST['password'])) {
			throw new Exception(PASSWORD_MISSING);
		}

		if (empty($_POST['repeat_password'])) {
			throw new Exception(REPEAT_PASSWORD_MISSING);
		}

		if (empty($_POST['d'])) {
		}
	}

	function validateNicknameAndPassword($nickname, $password, $repeatPassword) {
		if (!preg_match('/[A-Za-z0-9_]{3,16}/', $nickname)) {
			throw new Exception(INVALID_NICKNAME);
		}

		if (!ctype_alnum(str_replace(array('_'), '', $nickname)))
			throw new Exception('Недействительные символы в нике!');		

		if (!preg_match('/[\S]{6,25}/', $password)) {
			throw new Exception(UNSAFE_PASSWORD);
		}

		if (strcmp($password, $repeatPassword) != 0) {
			throw new Exception(DIFFERENT_PASSWORDS);
		}
	}


	function openDatabaseConnection() {
		$connection = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);

		if (mysqli_connect_errno($connection)) {
			throw new Exception(INTERNAL_ERROR);
		}

		return $connection;
	}

	function isCrackedUserRegistered($connection, $nickname) {
		$sql = "SELECT `uniqueId` FROM `user_profiles` WHERE `lastNickname` = '$nickname' AND `premiumId` IS NULL AND `hashedPassword` IS NOT NULL";
		$query = mysqli_query($connection, $sql);

		if (mysqli_num_rows($query) > 0) {
			throw new Exception(CRACKED_CLAIMED);
		}
	}

	function isPremiumExists($nickname)
	{
		$response = file_get_contents("https://api.mojang.com/users/profiles/minecraft/$nickname");

		if ($response)
			throw new Exception('Ник уже занят лицензионным пользователем');			
	}

	function isCrackedUserCanRegisterOnSerer($connection, $nickname) {
		$sql = "SELECT `uniqueId` FROM `user_profiles` WHERE `lastNickname` = '$nickname' AND `premiumId` IS NULL AND `hashedPassword` IS NULL";
		$query = mysqli_query($connection, $sql);

		if (mysqli_num_rows($query) > 0) {
			throw new Exception(CRACKED_REGISTER_ON_SERVER);
		}
	}

	function fetchPremiumId($nickname) {
		$response = file_get_contents("https://api.mojang.com/users/profiles/minecraft/$nickname");

		if (empty($response)) {
			return null;
		}

		return json_decode($response, true)['id'];
	}

	function isPremiumUserRegistered($connection, $nickname, $premiumId) {
		$sql = "SELECT `lastNickname` FROM `user_profiles` WHERE `premiumId` = '$premiumId'";
		$query = mysqli_query($connection, $sql);

		if (mysqli_num_rows($query) > 0) {
			$sql = "UPDATE `user_profiles` SET `lastNickname` = NULL WHERE `premiumId` = '$premiumId'";
			$user = mysqli_fetch_array($query);
			$lastNickname = $user['lastNickname'];

			if (strcasecmp($lastNickname, $nickname) != 0) {
				mysqli_query($connection, $sql);
			}

			throw new Exception(PREMIUM_ClAIMED);
		}

		$sql = "UPDATE `user_profiles` SET `lastNickname` = NULL WHERE `lastNickname` = '$nickname'";
		mysqli_query($connection, $sql);
	}

	function registerNewUser($connection, $nickname, $password) {
		$uniqueId;
		$address = $_SERVER['REMOTE_ADDR'];

		if (FIXED_UNIQUE_IDS) {
			$uniqueId = sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
				mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0x0fff) | 0x4000,
				mt_rand(0, 0x3fff) | 0x8000,
				mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
			);
		} else {
			$data = hex2bin(md5('OfflinePlayer:' . $nickname));
		    $data[6] = chr(ord($data[6]) & 0x0f | 0x30);
		    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		    $uniqueId = bin2hex($data);
		}


		$hash = password_hash ($password , PASSWORD_BCRYPT);
        $hashsub  =  substr($hash, 3);
        $hashedPassowrd = BCRYPT . $hashsub;

		$sql = "REPLACE INTO `user_profiles` VALUES ('$uniqueId', NULL, '$nickname', '$hashedPassowrd', NULL, NULL, NULL, '$address', CURRENT_TIMESTAMP, '$address', CURRENT_TIMESTAMP)";
		$query = mysqli_query($connection, $sql);

		if (!$query) {
			throw new Exception(INTERNAL_ERROR);
		}

		$_POST = array();

		throw new Exception(REGISTERED);
	}

?>

<!DOCTYPE html>
<html lang="en">

	<head>
		<title>Register Minecraft Account</title>

		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="author" content="Jakubson - https://www.spigotmc.org/conversations/add?to=Jakubson">

	 	<link rel="stylesheet" type="text/css" href="style.css">
		<link href="https://fonts.googleapis.com/css?family=Cousine" rel="stylesheet">
		<link rel="preconnect" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css2?family=PT+Sans&display=swap" rel="stylesheet">

	</head>

  <!-- Registration -->
	<body>
		<div class="main night-mode-available">

		<div class="reg" data-tilt data-tilt-max="4" data-tilt-speed="3000" data-tilt-perspective="1000">
		<form method="post" action="./index.php" class="glass">
			<center>
				<p>Достаточно зарегистрировать тут аккаунт <br> и зайти на <span>mc.bebland.net</span> <br>(Если у вас лицензионный аккаунт - просто зайдите на сервер)</p>

				<?php echo ((isset($error)) ? '<p><span>' . $error . '</span></p>' : null) ?>

				<input class="inp" type="hidden" name="sent" value="true">
				<input class="inp" type="text" name="nickname" placeholder="Игровой ник" value="<?php echo ((isset($_POST['nickname'])) ? $_POST['nickname'] : null) ?>">
				<input class="inp" type="password" name="password" placeholder="Пароль" value="<?php echo ((isset($_POST['password'])) ? $_POST['password'] : null) ?>">
				<input class="inp" type="password" name="repeat_password" placeholder="Повтор пароля" value="<?php echo ((isset($_POST['repeat_password'])) ? $_POST['repeat_password'] : null) ?>">

				<input class="inp" type="submit" class="regb" value="Register!">
				<p>уже есть аккаунт? <a href="">Войти</p></a>	
			</center>
		</form>
		<div class="image">
		<a href="https://discord.gg/Fnw5228"><img class="ddd" src="discord.svg">
		<a href="https://www.youtube.com/channel/UC89jcy9wjMy0K2-7XIHgJiA"><img src="youtube.svg">
		<a href="https://discord.gg/Fnw5228"><img src="bebland.svg">
		</div>
		</div>
			</div>


		<script type="text/javascript" src="vanilla-tilt.js"></script>
		
	</body>
</html>
