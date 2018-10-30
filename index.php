<?php
// some useful constants
const DISABLE_DATALIST = true;
const DISABLE_SELECT = true;
const EXCLUDE_UNWRITABLE_FOLDER = true;

// Checks if authentication is set
const DBNAME = '.htMembersOnly'; // must start with .ht for security
define('HT_ACCESS', __DIR__ .'/.htaccess');
define('DATABASE', __DIR__ .'/'.DBNAME);

# http://lebrument.free.fr/wordpress/wordpressfr/?p=50
# const DISABLE_AUTH = true; // Insecure, but some hostname don't support valid authentication (free.fr)

if(empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) { die(); }

// detects preferred language
$lang = 'en';
const PATTERN = '@^([a-z]{2}(?:-[A-Z]{2})?)\b.*$@';
foreach(
	array_map(
		function($value) { return preg_replace(PATTERN, "$1", $value); },
		explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])
	) as $value
) {
	$filename = __DIR__ ."/$value.php";
	if(is_readable($filename)) { $lang = $value; break; }
	if(strlen($value) > 2) {
		$shortcut = substr($value, 0, 2);
		$filename = __DIR__ ."/$shortcut.php";
		if(is_readable($filename)) { $lang = $shortcut; break; }
	}
}
require __DIR__ ."/$lang.php";

function noCache() {
	header('Expires: Tue, 03 Jul 2001 06:00:00 GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');
}

if(
	((!defined('DISABLE_AUTH') or empty(DISABLE_AUTH))) and
	(!file_exists(HT_ACCESS) or !file_exists(DATABASE))
) {
	noCache();
	require __DIR__ .'/auth.php';
	exit;
}

function process(&$uri, &$folderName) {
	if(
		// protection against hacking
		empty($_SERVER['HTTP_USER_AGENT']) or
		empty($uri) or
		empty($folderName) or
		!filter_has_var(INPUT_POST, 'token') or
		!filter_input(INPUT_POST, 'token', FILTER_VALIDATE_REGEXP,
			array('options' => array(
				'regexp' => '@[0-9a-f]@'
			))
		)
	) {
		return 'Formulaire invalide';
	}

	$token = $_POST['token'];
	if(
		!isset($_SESSION['token'][$token]) or
		$_SESSION['token'][$token] < time()
	) {
		echo "<!--\n";
		print("$token\n");
		print_r($_SESSION);
		print(time());
		echo "-->\n";
		return L_TIMEOUT;
	}

	$folder = $_SERVER['DOCUMENT_ROOT'].'/'.trim($folderName, '/');
	// echo "<!-- $folder -->\n";
	if(!is_dir($folder)) { return L_MISSING_TARGET; }
	if(!is_writable($folder)) { return nl2br(sprintf(L_UNWRITABLE_FOLDER, $folderName)); }

	$today = date('[D M d H:i:s]');
	error_log("$today \"$uri\" file requested from ${_SERVER['REMOTE_ADDR']}\n", 3, __DIR__ .'/report.log');

	if(preg_match('@^(?:https?|s?ftp)://@', $uri)) {
		// remote access
		$tmpFile = tempnam(sys_get_temp_dir(), 'ZZZ');
		$fp = fopen($tmpFile, 'w');
		if($fp === false) { return L_NO_TEMPFILE; }
		$log = fopen(__DIR__ .'/curl.log', 'a+');
		fwrite($log, date("=== D M d H:i:s ===\n")."Url: $uri\n");
		$ch = curl_init($uri);
		curl_setopt_array($ch, array(
			CURLOPT_FOLLOWLOCATION		=> true,
			CURLOPT_HEADER				=> false,
			CURLOPT_USERAGENT			=> $_SERVER['HTTP_USER_AGENT'],
			CURLOPT_WRITEHEADER			=> $log,
			CURLOPT_FILE				=> $fp
		));
		if(curl_exec($ch) !== true) {
			fclose($log);
			fclose($fp);
			$error = curl_error($ch);
			curl_close($ch);
			return $error;
		}
		fclose($log);
		fclose($fp);
		curl_close($ch);
		$filename = $tmpFile;
	} else {
		$filename = $_SERVER['DOCUMENT_ROOT'].'/'.ltrim($uri, '/'); // local file in this server
	}

	if(!file_exists($filename) or filesize($filename) == 0) {
		if(!empty($tmpFile)) { unlink($tmpFile); }
		return  L_NO_ZIP;
	}

	$zip = new ZipArchive;
	if($zip->open($filename) !== true) { return L_BAD_ZIP; }
	if(!$zip->extractTo($folder)) {
		return L_BAD_ZIP;
	}
	$zip->close();
	if(!empty($tmpFile)) { unlink($tmpFile); }

	return true;
}

class foldersTree {
	public $datas = false;
	private $__offset = false;

	public function __construct($root) {
		$root = rtrim($root, '/').'/';
		$this->datas = array();
		$this->__offset = strlen($root);
 		self::__scan($root);
	}

	private function __scan($root) {
		foreach(glob($root.'*', GLOB_ONLYDIR | GLOB_MARK) as $folder) {
			if(is_writable($folder)) {
				$this->datas[] = '\'' . substr($folder, $this->__offset) . '\'';
			}
			self::__scan($folder);
		}
	}
}

function sendJS() {
		$offset = strlen($_SERVER['DOCUMENT_ROOT']);

		// Create a datalist for all zip files from this server
		$zipsList = implode(",\n", array_map(
			function($zipname) use($offset) {
				return '\''. addslashes(substr($zipname, $offset)) .'\'';
			},
			array_merge(
				glob($_SERVER['DOCUMENT_ROOT'].'/*/*/*/*/*.zip'),
				glob($_SERVER['DOCUMENT_ROOT'].'/*/*/*/*.zip'),
				glob($_SERVER['DOCUMENT_ROOT'].'/*/*/*.zip'),
				glob($_SERVER['DOCUMENT_ROOT'].'/*/*.zip'),
				glob($_SERVER['DOCUMENT_ROOT'].'/*.zip')
			)
		));

		// Builds a select tag with all writable folders
		$buf = new foldersTree($_SERVER['DOCUMENT_ROOT']);
		$foldersList = implode(",\n", $buf->datas);
		header('Cache-Control: private'); // max-age doesn't work if ExpiresByType in .htaccess.
		header('Content-Type: application/javascript; charset=utf-8', true);

	echo <<< EOT
		const zipsList = [
$zipsList
		];
		const foldersList = [
$foldersList
		];

function extend(zipsList, foldersList) {
	const datalist = document.createElement('DATALIST')
	datalist.id = 'zip-list';
	zipsList.forEach(function(value) {
		const option = document.createElement('OPTION');
		option.value = value;
		datalist.appendChild(option);
	});
	document.body.appendChild(datalist);
	const form1 = document.forms[0];
	form1.elements.uri.setAttribute('list', 'zip-list');

	const currentFolder = form1.folder.value;
	const select = document.createElement('SELECT');
	select.id = 'id_folder';
	select.size = 10;
	select.required = true;
	foldersList.forEach(function(value) {
		const option = document.createElement('OPTION');
		option.textContent = value;
		if(currentFolder == currentFolder) { option.selected = true; }
		select.appendChild(option);
	});
	form1.insertBefore(select, form1.folder);
	form1.removeChild(form1.folder);
	form1.removeChild(foldersBtn);
	form1.classList.add('large');
	select.name = 'folder';

	document.getElementById('spinner-overlay').classList.remove('active');
	sessionStorage.setItem('unzip-me', 'on');
}

extend(zipsList, foldersList);\n
EOT;
	}

$zipEnabled = false;
if(class_exists('ZipArchive')) {

	if(isset($_GET['getDatas'])) {
		sendJS();
		exit;
	}

	$zipEnabled = true;
	$uri = filter_input(INPUT_POST, 'uri', FILTER_SANITIZE_URL, FILTER_NULL_ON_FAILURE);
	$folderName = filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_URL, FILTER_NULL_ON_FAILURE);
	$status = false;
	noCache();
	session_start();
	if(!empty($_POST)) {
		$status = process($uri, $folderName);
	}
	session_unset(); // cleanup $_SESSION
	$token = sha1(mt_rand(0, 1000000));
	$_SESSION['token'][$token] = time() + 15 * 60;
} else {
	$status = L_MISSING_ZIP_MODULE;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=Edge" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo L_INDEX_TITLE; ?></title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		html { background-color: #444; }
		body { font: 12pt 'Noto Sans', Arial, Sans-Serif; }
		main { margin: 50vh auto 0; max-width: 36rem; background-color: #eee; transform: translateY(-50%); }
		form { display: grid; padding: 0.5rem; grid-template-columns: 10rem auto; grid-gap: 0.5rem; }
		label { justify-self: end; }
		form.large input[type="submit"] { grid-column: 1 / -1; }
		.center { text-align: center; }
		.error { color: red; }
		.success { color: #fff; background-color: green; }
		@-webkit-keyframes fbk {
			0%, 40%, 100% { height: 40%; }
			20% { height: 100%; }
		}
		@keyframes fbk {
			0%, 40%, 100% { height: 40%; }
			20% { height: 100%; }
		}
		#spinner-overlay { display: none; }
		#spinner-overlay.active {
			display: block;
			position: fixed;
			top: 0; left: 0; height: 100vh; width: 100vw;
			background-color: #666e;
		}
		.spinner {
			display: grid;
			grid-template-columns: repeat(5, 1.2rem);
			grid-column-gap: 0.3rem;
			justify-content: center;
			align-items: center;
			height: 10rem;
			margin-top: calc(50vh - 5rem); /* height / 2 */
		}
		.spinner div { background-color: #cef; animation: fbk 1.2s cubic-bezier(0, 0.5, 0.5, 1) infinite; }
		.spinner div:nth-of-type(1) { animation-delay: -1.2s; }
		.spinner div:nth-of-type(2) { animation-delay: -1.1s; }
		.spinner div:nth-of-type(3) { animation-delay: -1.0s; }
		.spinner div:nth-of-type(4) { animation-delay: -0.9s; }
		.spinner div:nth-of-type(5) { animation-delay: -0.8s; }
	</style>
</head><body>
	<main>
<?php
if(is_string($status)) {
?>
		<div class="error center"><?= $status ?></div>
<?php
} elseif($status === true) {
?>
		<div class="success center"><?php printf(L_SUCCESS, basename($uri)); ?></div>
<?php
	$uri = '';
}
if($zipEnabled) {
?>
		<form id="form1" method="post" autocomplete="off">
			<label for="id_uri"><?php echo L_URI; ?></label>
			<input name="uri" id="id_uri" value="<?= $uri ?>" placeholder="<?php echo L_URI_PLACEHOLDER; ?>"
				pattern="^(/.*\.zip|https?://\w.*|s?ftp://\w.*)" autofocus required />
			<label for="id_folder"><?php echo L_TARGET; ?></label>
			<input type="text" name="folder" value="<?= $folderName; ?>" placeholder="<?php echo L_TARGET_PLACEHOLDER; ?>" id="id_folder" required />
			<input type="hidden" name="token" value="<?= $token ?>" />
			<input type="button" id="foldersBtn" value="<?php echo L_FOLDERS_LIST ?>" />
			<input type="submit" id="submit" value="<?php echo L_SUBMIT; ?>" />
		</form>
	</main>
	<div id="spinner-overlay">
		<div class="spinner">
			<div></div>
			<div></div>
			<div></div>
			<div></div>
			<div></div>
		</div>
	</div>
	<script type="text/javascript">
		'use strict';
		document.getElementById('form1').addEventListener('submit', function(event) {
			document.getElementById('spinner-overlay').classList.add('active');
		});

		function addScript() {
			const overlay = document.getElementById('spinner-overlay');
			overlay.classList.add('active');
			const script = document.createElement('SCRIPT');
			script.type = 'text/javascript';
			script.src = window.location.href.replace(/(?:\/|\/index.php)?$/, '/index.php?getDatas');
			document.head.appendChild(script);
		}

		if(sessionStorage.getItem('unzip-me') === 'on') {
			addScript();
		} else {
			const foldersBtn = document.getElementById('foldersBtn');
			if(foldersBtn != null) {
				foldersBtn.onclick = function(event) {
					event.preventDefault();
					addScript();
				};
			}
		}

	</script>
<?php
}
?>
</body></html>
