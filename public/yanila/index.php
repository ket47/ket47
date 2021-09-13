<?php

define('BAY_UPDATE_URL','http://github.com/ket47/ket47/archive/master.zip');

class UpdateInstaller {
    private $link;
    private $dirParent;
    private $dirApplication;
    private $dirUnpack;
    private $dirBackup;
    private $zipPath;
    private $dirUpdate;
    private $dirDbBackup;
    
    public function index(){
        $url =  "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        ?>
        <a href="<?=$url?>&step=download" target="action">Download</a><br>
        <a href="<?=$url?>&step=swap" target="action">Swap</a><br>
        <a href="<?=$url?>&step=install" target="action">Install</a><br>
        <?php
    }
    

    function __construct() {
    	$this->dirParent = realpath('../../../');
    	$this->dirDbBackup=$this->dirParent."/TEZKEL_STORAGE/";
    	
    	$this->dirApplication     = $this->dirParent . '/application/';
    	$this->dirUnpack   = $this->dirParent . '/_update/';
    	$this->dirBackup   = $this->dirParent . '/_backup/';
        
    	$this->zipPath     = $this->dirUnpack . '/update.zip';
    	$this->dirUpdate = $this->dirUnpack . "/ket47-master/";
    }
    
    public function appUpdate($action = 'download') {
    	switch( $action ){
    	    case 'download':
                return $this->updateDownload(BAY_UPDATE_URL, $this->zipPath);
                return $this->updateUnpack();
                break;
    	    case 'swap':
                return $this->updateSwap();
                break;
    	    case 'install':
                return $this->freshDbInstall();
                break;
            default:
                $this->index();
                break;
    	}
    }

    private function updateDownload($updateUrl, $updateFile) {
    	mkdir(dirname($updateFile), 0700, true);
    	if(!copy($updateUrl,$updateFile))
        {
            $errors= error_get_last();
            echo "COPY ERROR: ".$errors['type'];
            echo "<br />\n".$errors['message'];
            return false;
        }
        return true;
    }

    private function updateUnpack() {
    	$this->delTree($this->dirUpdate);
    	$zip = new ZipArchive;
    	if ($zip->open($this->zipPath) === TRUE) {
    	    $zip->extractTo($this->dirUnpack);
    	    $zip->close();
    	    return true;
    	} else {
    	    return false;
    	}
    }

    private function updateSwap() {
    	if (file_exists($this->dirBackup)) {
            exec("rm -r {$this->dirBackup}");
    	}
        mkdir($this->dirBackup, 0700, true);

    	
    	if ( file_exists($this->dirApplication) && file_exists($this->dirUpdate) ) {
            exec("mv {$this->dirApplication}app {$this->dirBackup}app 2>&1",$output);
            exec("mv {$this->dirUpdate}app {$this->dirApplication}app 2>&1",$output);
            
            exec("mv {$this->dirApplication}public {$this->dirBackup}public 2>&1",$output);
            exec("mv {$this->dirUpdate}public {$this->dirApplication}public 2>&1",$output);
            exec("cp {$this->dirApplication}../.env {$this->dirApplication}.env 2>&1",$output);
    	} else {
            echo "{$this->dirApplication} or {$this->dirUpdate} not exists";
        }
    	
        exec("rm -r {$this->dirUnpack}");
    	return false;
    }
    
    
    
    
    
    
    

    private function freshDbInstall() {
    	if( $this->checkAdminExists() ){
    	    return true;//'admin_exists';
    	}
    	$file = str_replace("\\", "/", $this->dirApplication . '/install/fresh_db_dump.sql');
    	$this->query("CREATE DATABASE IF NOT EXISTS " . BAY_DB_NAME." DEFAULT CHARACTER SET utf8");
    	return $this->backupImportExecute($file);
    }
    
    public function request($index){
    	return isset($_REQUEST[$index])?addslashes($_REQUEST[$index]):null;
    }
    
    private function signup(){
    	if( $this->checkAdminExists() ){
    	    return 'admin_exists';
    	}
    	$first_name=  $this->request('first_name');
    	$last_name=  $this->request('last_name');
    	$user_login=  $this->request('user_login');
    	$user_pass=   $this->request('user_pass');
    	$blank_set=   $this->request('blank_set');
    	if( preg_match('/^[a-zA-Z_0-9]{3,}$/',$user_login) && preg_match('/^[a-zA-Z_0-9]{3,}$/',$user_pass) ){
    	    $pass_hash = md5($user_pass);
    	    $this->query("REPLACE INTO " . BAY_DB_NAME . ".pref_list SET active_company_id=(SELECT company_id FROM " . BAY_DB_NAME . ".companies_list WHERE is_active=1 LIMIT 1),pref_name='blank_set',pref_value='$blank_set'");
    	    $this->query("INSERT INTO " . BAY_DB_NAME . ".user_list SET first_name='$first_name',last_name='$last_name',user_login='$user_login',user_pass='$pass_hash',user_level=4");
    	    return mysqli_errno($this->link)?mysqli_errno($this->link):'admin_added';
    	} else {
    	    return 'login_pass_not_match';
    	}
    }

    private function checkAdminExists() {
    	$row = $this->query("SELECT user_id FROM " . BAY_DB_NAME . ".user_list WHERE user_level=4");
    	return $row;
        }
    
        private function checkInstalled(){
    	$status='';
    	if( file_exists($this->dirApplication) && file_exists($this->dirApplication.'/index.php') ){
    	    $status.=' files_ok';
    	}
    	if( $this->query("SHOW DATABASES LIKE '" . BAY_DB_NAME . "'") ){
    	    $status.=' db_ok';
    	}
    	if( $this->checkAdminExists() ){
    	    $status.=' admin_ok';
    	}
    	return $status;
    }
    
    private function setupConf() {
    	$conf_file = $this->dirApplication . "/conf" . rand(1, 1000);
    	$conf = '[client]
    	    user="' . BAY_DB_USER . '"
    	    password="' . BAY_DB_PASS . '"';
    	file_put_contents($conf_file, $conf);
    	return $conf_file;
    }

    private function query($query) {
    	if (!isset($this->link)) {
    	    $this->link = mysqli_connect('localhost', BAY_DB_USER, BAY_DB_PASS);
    	}
    	mysqli_query($this->link, "SET NAMES utf8");
    	$result = mysqli_query($this->link, $query);
    	if (is_bool($result)) {
    	    return $result;
    	}
    	return mysqli_fetch_object($result);
    }
    
    private function backupImportExecute($file) {
    	$output = [];
    	$conf_file = $this->setupConf();
    	$path_to_mysql = $this->query("SHOW VARIABLES LIKE 'basedir'")->Value;
    	exec("$path_to_mysql/bin/mysql --defaults-file=$conf_file " . BAY_DB_NAME . " <" . $file . " 2>&1", $output);
    	unlink($conf_file);
    	if (count($output)) {
    	    if( !file_exists($this->dirDbBackup) ){
    		mkdir($this->dirDbBackup);
    	    }
    	    file_put_contents($this->dirDbBackup . date('Y-m-d_H-i-s') . '-IMPORT.log', implode("\n", $output));
    	    return false;
    	}
    	return true;
    }
    private function delTree($dir) {
    	if (!file_exists($dir)) {
    	    return true;
    	}
    	$files = array_diff(scandir($dir), array('.', '..'));
    	foreach ($files as $file) {
    	    (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
    	}
    	return rmdir($dir);
    }

}

if ( isset($_GET['subhanaka']) && $_GET['subhanaka']='tabarakasmuka' ) {
    $UpdateInstaller = new UpdateInstaller();
    $step = $UpdateInstaller->request('step');
    echo $UpdateInstaller->appUpdate($step);
} else {
    http_response_code(404);
    ?>
    <!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
    <html><head>
    <title>404 Not Found</title>
    </head><body>
    <h1>Not Found</h1>
    <p>The requested URL was not found on this server.</p>
    <hr>
    <address>Apache/2.4.10 (Unix) Server at Port 80</address>
    </body></html>
    <?php
}