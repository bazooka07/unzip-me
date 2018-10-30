<?php
if(!defined('HT_ACCESS') or !defined('DATABASE')) {
	header('Content-Type: text/plain; charset:ascii');
	exit('Goodbye Baby..');
}

// http://aspirine.org/htpasswd_en.html (Javascript)

// https://www.virendrachandak.com/techtalk/using-php-create-passwords-for-htpasswd-file/
function crypt_apr1_md5($plainpasswd) {
	// APR1-MD5 encryption method (windows compatible)
	$salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
	$len = strlen($plainpasswd);
	$text = $plainpasswd.'$apr1$'.$salt;
	$bin = pack('H32', md5($plainpasswd.$salt.$plainpasswd));
	for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
	for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $plainpasswd{0}; }
	$bin = pack('H32', md5($text));
	for($i = 0, $iMax = 1000; $i < $iMax; $i++) {
		$new = ($i & 1) ? $plainpasswd : $bin;
		if ($i % 3) $new .= $salt;
		if ($i % 7) $new .= $plainpasswd;
		$new .= ($i & 1) ? $bin : $plainpasswd;
		$bin = pack('H32', md5($new));
	}
	$tmp = '';
	for ($i = 0; $i < 5; $i++) {
		$k = $i + 6;
		$j = $i + 12;
		if ($j == 16) $j = 5;
		$tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
	}
	$tmp = chr(0).chr(0).$bin[11].$tmp;
	$tmp = strtr(
		strrev(substr(base64_encode($tmp), 2)),
		'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
		'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
	);

	return "$"."apr1"."$".$salt."$".$tmp;
}

if(!empty($_POST)) {
	$params = filter_input_array(INPUT_POST, array(
		'mylogin'	=> FILTER_SANITIZE_URL,
		'secret1'	=> FILTER_SANITIZE_URL,
		'secret2'	=> FILTER_SANITIZE_URL
	));
	if(
		array_reduce(
			array_values($params),
			function($previous, $item) { return $previous and !empty($item); },
			true
		) and
		$params['secret1'] === $params['secret2'] and
		is_writable(__DIR__)
	) {
		if(preg_match('@\.free\.fr$@', $_SERVER['SERVER_NAME'])) {
			// http://les.pages.perso.chez.free.fr/le-htaccess-des-pages-perso.io
			$db = dirname($_SERVER['SCRIPT_NAME']).'/'.DBNAME;
			$authUserFile = "PerlSetVar AuthFile $db";
			$password = substr($params['secret1'], 0, 25); // no encryption
		} else {
			// Sometimes dirname(...) may be different of __DIR__ (lws.fr)
			$db = dirname($_SERVER['SCRIPT_FILENAME']).'/'.DBNAME;
			$authUserFile = "AuthUserFile $db";
			$password = crypt_apr1_md5(substr($params['secret1'], 0, 25));
		}

		$realm = L_REALM;
		$content = <<< CONTENT
AuthType Basic
AuthName "$realm"
$authUserFile
Require valid-user

<Files "*.log">
	order deny,allow
	deny from all
</Files>

<IfModule mod_expires.c>
	ExpiresActive on
	ExpiresByType application/javascript "access plus 20 minutes"
</ifmodule>\n
CONTENT;
		file_put_contents(HT_ACCESS, $content);
		chmod(HT_ACCESS, 0644);
		file_put_contents(
			DATABASE,
			substr($params['mylogin'], 0, 25).':'.$password
		);
		chmod(DATABASE, 0644);
		header('Location: index.php');
		exit;
	} else {
		$error = L_ERROR_FOUND;
	}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=Edge" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo L_AUTH_TITLE; ?></title>
	<style>
		* { margin: 0; padding:0; }
		html { background-color: #666; }
		body { max-width: 25rem; margin: 50vh auto 0; background-color: #eee; font: 12pt 'Noto Sans', Arial, Sans-Serif; transform: translateY(-50%); }
		form { display: grid; grid-template-columns: 8rem 1fr; grid-gap: 0.3rem; padding: 0.3rem; }
		label { justify-self: end; }
		input[type="submit"] { grid-column: 1 / -1; }
		.error { color: red; text-align: center; }
	</style>
</head><body>
<?php
if(!empty($error)) {
	echo <<< ERROR
	<div class="error">$error</div>\n
ERROR;
}
?>
	<form id="form_auth" method="post" autocomplete="off">
		<label for="id_mylogin"><?php echo L_MYLOGIN; ?></label>
		<input type="text" id="id_mylogin" name="mylogin" maxlength="25" required>
		<label for="id_secret1"><?php echo L_SECRET1; ?></label>
		<input type="password" id="id_secret1" name="secret1" maxlength="25" required>
		<label for="id_secret2"><?php echo L_SECRET2; ?></label>
		<input type="password" id="id_secret2" name="secret2" maxlength="25" placeholder="<?php echo L_SECRET2_PLACEHOLDER; ?>" required>
		<input type="submit" />
	</form>
	<script type="text/javascript">
		document.getElementById('form_auth').addEventListener('submit', function(event) {
			if(document.getElementById('id_secret1').value != document.getElementById('id_secret2').value) {
				alert('<?php echo L_PASSWORDS_MISMATCH; ?>');
				event.preventDefault();
			}
		});
	</script>
</body></html>
