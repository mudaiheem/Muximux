<?php
require_once(dirname(__FILE__).'/ping/ping.php');

function getThemeFile() {
	$config = new Config_Lite(CONFIG);
	$item = $config->get('general', 'theme', 'Classic');
	$fileName = 'css/theme/'.$item.'.css';
	if(file_exists($fileName)) {
		return $fileName;
	}

	// Handle case insensitive requests
	$directoryName = dirname($fileName);
	$fileArray = glob($directoryName . '/*', GLOB_NOSORT);
	$fileNameLowerCase = strtolower($fileName);
	foreach($fileArray as $file) {
		if(strtolower($file) == $fileNameLowerCase) {
			return $file;
		}
	}
}


// Ping one or several hosts.
// Will return true or false if only one host is specified,
// returns an associative array if an array is provided.
function ping($hosts) {
	$singleHost = false;
	$results = [];
	if (is_string($hosts)) {
		$hosts = [$hosts];
		$singleHost = true;
	}
	foreach ($hosts as $host) {
		$ping = new JJG\Ping($host,128,5);
		$latency = $ping->ping();
		if (!$singleHost) array_push($results,($latency !== false)); else return ($latency !== false);
	}
	return (array_combine($hosts,$results));
}

// Appends lines to file and makes sure the file doesn't grow too much
// You can supply a level, which should be a one of (ERROR, WARN, DEBUG, INFO)
// If a level is not supplied, it will be assumed to be DEBUG.
function write_log($text,$level=null,$caller=false) {
	if ($level === null) {
		$level = 'DEBUG';
	}
	if (isset($_GET['pollPlayer'])) return;
	$caller = $caller ? $caller : getCaller();
	$filename = LOGPATH;
	$text = '['.date(DATE_RFC2822) . '] ['.$level.'] ['.$caller . "] - " . trim($text) . PHP_EOL;
	if (!file_exists($filename)) { touch($filename); chmod($filename, 0666); }
	if (filesize($filename) > 2*1024*1024) {
		$filename2 = "$filename.old";
		if (file_exists($filename2)) unlink($filename2);
		rename($filename, $filename2);
		touch($filename); chmod($filename,0666);
	}
	if (!is_writable($filename)) die;
	if (!$handle = fopen($filename, 'a+')) die;
	if (fwrite($handle, $text) === FALSE) die;
	fclose($handle);
}

// Get the name of the function calling write_log
function getCaller() {
	$trace = debug_backtrace();
	$useNext = false;
	$caller = false;
	//write_log("TRACE: ".print_r($trace,true),null,true);
	foreach ($trace as $event) {
		if ($useNext) {
			if (($event['function'] != 'require') && ($event['function'] != 'include')) {
				$caller .= "::" . $event['function'];
				break;
			}
		}
		if ($event['function'] == 'write_log') {
			$useNext = true;
			// Set our caller as the calling file until we get a function
			$file = pathinfo($event['file']);
			$caller = $file['filename'] . "." . $file['extension'];
		}
	}
	return $caller;
}


function saveConfig(Config_Lite $inConfig) {
	try {
		$inConfig->save();
	} catch (Config_Lite_Exception $e) {
		echo "\n" . 'Exception Message: ' . $e->getMessage();
		write_log('Error saving configuration.','ERROR');
	}
	$configFile = CONFIG;
	$cache_new = "'; <?php die('Access denied'); ?>"; // Adds this to the top of the config so that PHP kills the execution if someone tries to request the config-file remotely.
	$cache_new .= file_get_contents($configFile);
	file_put_contents($configFile,$cache_new);
}


// Echo a message to the user
function setStatus($message) {
	$scriptBlock = "<script language='javascript'>alert(\"" . $message . "\");</script>";
	echo $scriptBlock;
}

// This might be excessive for just grabbing one theme value from CSS,
// but if we ever wanted to make a full theme editor, it could be handy.

function parseCSS($file,$searchSelector,$searchAttribute){
	$css = file_get_contents($file);
	preg_match_all( '/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $css, $arr);
	$result = false;
	foreach ($arr[0] as $i => $x){
		$selector = trim($arr[1][$i]);
		if ($selector == $searchSelector) {
			$rules = explode(';', trim($arr[2][$i]));
			$rules_arr = array();
			foreach ($rules as $strRule){
				if (!empty($strRule)){
					$rule = explode(":", $strRule);
					if (trim($rule[0]) == $searchAttribute) {
						$result = trim($rule[1]);
					}
				}
			}
		}
	}
	return $result;
}

/**
 * Generate the JSON variable for the <code>source</code> option for fontIconPicker
 *
 * You will need to assign it to a variable inside JavaScript code
 * @link https://github.com/micc83/fontIconPicker fontIconPicker Project Page
 *
 * @param  array  $icomoon_icons The original variable generated by this script
 * @param  string $by            What to print the value by. Possibilities are class or key
 * @return string                The JSON which can be assigned to a variable. See example
 */
function imii_generate_fip_source_json( $icomoon_icons, $by = 'class' ) {
	$json = array();
	$by = strtolower( $by );
	foreach ( $icomoon_icons as $icons ) {
		$icon_set = array();
		if ( isset( $icons['elements'] ) ) {
			foreach ( $icons['elements'] as $ic_key => $ic_name ) {
				$val = $ic_key;
				if ( $by == 'class' ) {
					$val = htmlspecialchars( $icons['element_classes'][$ic_key] );
				}
				$icon_set[] = $val;
			}
		}
		$json[$icons['label']] = $icon_set;
	}
	return json_encode( $json );
}

/**
 * Generate the JSON variable for the <code>searchSource</code> option for fontIconPicker
 *
 * You will need to assign it to a variable inside JavaScript code
 * @link https://github.com/micc83/fontIconPicker fontIconPicker Project Page
 *
 * @param  array  $icomoon_icons The original variable generated by this script
 * @return string                The JSON which can be assigned to a variable. See example
 */
function imii_generate_fip_search_json( $icomoon_icons ) {
	$json = array();
	foreach ( $icomoon_icons as $icons ) {
		$icon_set = array();
		if ( isset( $icons['elements'] ) ) {
			foreach ( $icons['elements'] as $ic_key => $ic_name ) {
				$icon_set[] = $ic_name;
			}
		}
		$json[$icons['label']] = $icon_set;
	}
	return json_encode( $json );
}




/**
 * Generate HTML SELECT OPTION along with OPTGROUP
 *
 * You will need to provide the SELECT element yourself as this only generates the optgroup and option elements
 *
 * @param  array  $icomoon_icons The original variable generated by this script
 * @param  string $by            What to print the value by. Possibilities are class, unicode, hex|hexadecimal or key
 * @return string                The optgroup and option for you to echo
 */
function imii_generate_select_options( $icomoon_icons, $by = 'class' ) {
	$return = '';
	$by = strtolower( $by );
	foreach ( $icomoon_icons as $icons ) {
		$return .= '<optgroup label="' . htmlspecialchars( $icons['label'] ) . '">';
		if ( isset( $icons['elements'] ) ) {
			foreach ( $icons['elements'] as $ic_key => $ic_name ) {
				$val = $ic_key;
				if ( $by == 'class' ) {
					$val = htmlspecialchars( $icons['element_classes'][$ic_key] );
				} else if ( $by == 'unicode' ) {
					$val = '&#x' . dechex( $ic_key ) . ';';
				} else if ( $by == 'hex' || $by == 'hexadecimal' ) {
					$val = dechex( $ic_key );
				}
				$return .= '<option value="' . $val . '">' . $ic_name . '</option>';
			}
		}
		$return .= '</optgroup>';
	}
	return $return;
}


// This is used by our login script to determine session state
function is_session_started() {
	if ( php_sapi_name() !== 'cli' ) {
		if ( version_compare(phpversion(), '5.4.0', '>=') ) {
			return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
		} else {
			return session_id() === '' ? FALSE : TRUE;
		}
	}
	return FALSE;
}


// Copy a directory recursively - used to move updates after extraction
function cpy($source, $dest){
	if(is_dir($source)) {
		$dir_handle=opendir($source);
		while($file=readdir($dir_handle)){
			if($file!="." && $file!=".."){
				if(is_dir($source."/".$file)){
					if(!is_dir($dest."/".$file)){
						mkdir($dest."/".$file);
					}
					cpy($source."/".$file, $dest."/".$file);
				} else {
					copy($source."/".$file, $dest."/".$file);
				}
			}
		}
		closedir($dir_handle);
	} else {
		copy($source, $dest);
	}
}

// Recursively delete the contents of a directory
function deleteContent($path){
	try{
		$iterator = new DirectoryIterator($path);
		foreach ( $iterator as $fileinfo ) {
			if($fileinfo->isDot())continue;
			if($fileinfo->isDir()){
				if(deleteContent($fileinfo->getPathname()))
					@rmdir($fileinfo->getPathname());
			}
			if($fileinfo->isFile()){
				@unlink($fileinfo->getPathname());
			}
		}
	} catch ( Exception $e ){
		// write log
		return false;
	}
	return true;
}

function setStartUrl() {
	$file = ( file_exists(dirname(__FILE__)."/manifest.json")) ? dirname(__FILE__)."/manifest.json" : dirname(__FILE__)."/manifest-template.json";
	$json = json_decode(file_get_contents($file),true);
	$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	if (! $json) die();
	if ($json['start_url'] !== $url) {
		$json['start_url'] = $url;
		try {
			file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT));
		} catch(Exception $e) {
			write_log("Exception creating manifest.","ERROR");
		}
	}
}

// Can we execute commands?
function exec_enabled() {
	$disabled = explode(', ', ini_get('disable_functions'));
	return !in_array('exec', $disabled);
}



function serverProtocol() {
	return (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')	|| $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://');
}

// Check if we can open a file.
function openFile($file, $mode) {
	if ((file_exists($file) && (!is_writable(dirname($file)) || !is_writable($file))) || !is_writable(dirname($file))) { // If file exists, check both file and directory writeable, else check that the directory is writeable.
		$message = 'Either the file '. $file .' and/or it\'s parent directory is not writable by the PHP process. Check the permissions & ownership and try again.';
		if (PHP_SHLIB_SUFFIX === "so") { //Check for POSIX systems.
			$message .= "  Current permission mode of ". $file. " is " .decoct(fileperms($file) & 0777);
			$message .= "  Current owner of " . $file . " is ". posix_getpwuid(fileowner($file))['name'];
			$message .= "  Refer to the README on instructions how to change permissions on the aforementioned files.";
		} else if (PHP_SHLIB_SUFFIX === "dll") {
			$message .= "  Detected Windows system, refer to guides on how to set appropriate permissions."; //Can't get fileowner in a trivial manner.
		}
		write_log($message,'E');
		setStatus($message);
		exit;
	}
	return fopen($file, $mode);
}