<?php
	if(!defined('MODX_BASE_PATH')){die('What are you doing? Get out of here!');}
	require(MODX_BASE_PATH.'assets/snippets/clouddamp/class_webdav_client.php');
	require(MODX_BASE_PATH.'assets/snippets/clouddamp/class_mysqldumper.php');
	global $path;
	
	switch($service){
		case 'yandex': 
			$service = 'ssl://webdav.yandex.ru';
			break;
		default: 
			$service = '';
	}
	$user = (isset($user)?$user: '');
	$pass = (isset($pass)?$pass: '');
	$delfile = (isset($delfile)?$delfile:false);
	$foldname = (isset($foldname)?$foldname:'backups');
	
	function callBack(&$dumpstring){
		global $modx;
		$today = $modx->toDateFormat(time(),'dateOnly');
		$today = str_replace('/', '-', $today);
		$today = strtolower($today);
		if(!headers_sent()){
			header('Expires: 0');
			header('Cache-Control: private');
			header('Pragma: cache');
			header('Content-type: application/download');
			header("Content-Disposition: attachment; filename={$today}_database_backup.sql");
		}
		echo $dumpstring;
		return true;
	}
	function snapshot(&$dumpstring){
		global $path;
		file_put_contents($path,$dumpstring,FILE_APPEND);
		return true;
	}
	function parsePlaceholder($tpl='', $ph=array()){
		if(empty($ph) || empty($tpl)) return $tpl;
		foreach($ph as $k=>$v){
			$k = "[+{$k}+]";
			$tpl = str_replace($k, $v, $tpl);
		}
		return $tpl;
	}
	$modx->config['snapshot_path'] = MODX_BASE_PATH.'assets/backup/';
	if(!is_dir(rtrim($modx->config['snapshot_path'],'/'))){
		mkdir(rtrim($modx->config['snapshot_path'],'/'));
		@chmod(rtrim($modx->config['snapshot_path'],'/'), 0777);
	}
	if(!file_exists("{$modx->config['snapshot_path']}.htaccess")){
		$htaccess = "order deny,allow\ndeny from all\n";
		file_put_contents("{$modx->config['snapshot_path']}.htaccess",$htaccess);
	}
	if(!is_writable(rtrim($modx->config['snapshot_path'],'/'))){
		echo parsePlaceholder($_lang["bkmgr_alert_mkdir"],$modx->config['snapshot_path']);
		exit;
	}
	$today = $modx->toDateFormat(time());
	$today = str_replace(array('/',' '), '-', $today);
	$today = str_replace(':', '_', $today);
	$today = strtolower($today);
	$path = "{$modx->config['snapshot_path']}{$today}.sql";
	@set_time_limit(120);
	$dumper = new Mysqldumper($database_server, $database_user, $database_password, $dbase);
	$dumper->setDroptables(true);
	$dumpfinished = $dumper->createDump('snapshot');
	if($dumpfinished){
		$wdc = new webdav_client();
		$wdc->set_server($service);
		$wdc->set_port(443);
		$wdc->set_user($user);
		$wdc->set_pass($pass);
		$wdc->set_protocol(1);
		$wdc->set_debug(false);
		if ($wdc->open()){ 
			if ($wdc->check_webdav()){
				$http_status = $wdc->mkcol("/{$foldname}/".$modx->config['site_name']);
				$http_status = $wdc->put_file("/{$foldname}/".$modx->config['site_name']."/{$today}.sql", "{$modx->config['snapshot_path']}{$today}.sql");
				if($delfile)unlink("{$modx->config['snapshot_path']}{$today}.sql");
			}
		}
	}
?>