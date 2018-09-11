<?PHP
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if(in_array('sha512', hash_algos())) {
	ini_set('session.hash_function', 'sha512');
}
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
	ini_set('session.cookie_secure', 1);
	header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload;");
}
session_start();

require_once('../other/config.php');

function getclientip() {
	if (!empty($_SERVER['HTTP_CLIENT_IP']))
		return $_SERVER['HTTP_CLIENT_IP'];
	elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif(!empty($_SERVER['HTTP_X_FORWARDED']))
		return $_SERVER['HTTP_X_FORWARDED'];
	elseif(!empty($_SERVER['HTTP_FORWARDED_FOR']))
		return $_SERVER['HTTP_FORWARDED_FOR'];
	elseif(!empty($_SERVER['HTTP_FORWARDED']))
		return $_SERVER['HTTP_FORWARDED'];
	elseif(!empty($_SERVER['REMOTE_ADDR']))
		return $_SERVER['REMOTE_ADDR'];
	else
		return false;
}

if (isset($_POST['logout'])) {
	echo "logout";
    rem_session_ts3($rspathhex);
	header("Location: //".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\'));
	exit;
}

if (!isset($_SESSION[$rspathhex.'username']) || $_SESSION[$rspathhex.'username'] != $webuser || $_SESSION[$rspathhex.'password'] != $webpass || $_SESSION[$rspathhex.'clientip'] != getclientip()) {
	header("Location: //".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\'));
	exit;
}

if (isset($_POST['update']) && $_POST['csrf_token'] != $_SESSION[$rspathhex.'csrf_token']) {
	echo $lang['errcsrf'];
	rem_session_ts3($rspathhex);
	exit;
}

require_once('nav.php');

if(!isset($_POST['number']) || $_POST['number'] == "yes") {
	$_SESSION[$rspathhex.'showexcepted'] = "yes";
	$filter = " WHERE `except`='0'";
} else {
	$_SESSION[$rspathhex.'showexcepted'] = "no";
	$filter = "";
}

if(($dbuserdata = $mysqlcon->query("SELECT `uuid`,`cldbid`,`name` FROM `$dbname`.`user` $filter ORDER BY `name` ASC")) === false) {
	$err_msg = "DB Error: ".print_r($mysqlcon->errorInfo(), true); $err_lvl = 3;
}
$user_arr = $dbuserdata->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['update']) && $_SESSION[$rspathhex.'username'] == $webuser && $_SESSION[$rspathhex.'password'] == $webpass && $_SESSION[$rspathhex.'clientip'] == getclientip() && $_POST['csrf_token'] == $_SESSION[$rspathhex.'csrf_token']) {
	$setontime = 0;
	if($_POST['setontime_day']) { $setontime = $setontime + $_POST['setontime_day'] * 86400; }
	if($_POST['setontime_hour']) { $setontime = $setontime + $_POST['setontime_hour'] * 3600; }
	if($_POST['setontime_min']) { $setontime = $setontime + $_POST['setontime_min'] * 60; }
	if($_POST['setontime_sec']) { $setontime = $setontime + $_POST['setontime_sec']; }
	if($setontime == 0) {
		$err_msg = $lang['errseltime']; $err_lvl = 3;
	} elseif($_POST['user'] == NULL) {
		$err_msg = $lang['errselusr']; $err_lvl = 3;
	} else {
		$allinsertdata = '';
		$succmsg = '';
		$nowtime = time();
		foreach($_POST['user'] as $uuid) {
			$setontime = $setontime * -1;
			$allinsertdata .= "('".$uuid."', ".$nowtime.", ".$setontime."),";
			$succmsg .= sprintf($lang['sccupcount'],$setontime,$uuid)."<br>";
		}
		$allinsertdata = substr($allinsertdata, 0, -1);
		if($mysqlcon->exec("INSERT INTO `$dbname`.`admin_addtime` (`uuid`,`timestamp`,`timecount`) VALUES $allinsertdata;") === false) {
			$err_msg = $lang['isntwidbmsg'].print_r($mysqlcon->errorInfo(), true); $err_lvl = 3;
		} else {
			$err_msg = substr($succmsg,0,-4); $err_lvl = NULL;
		}
	}
}

$_SESSION[$rspathhex.'csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
?>
		<div id="page-wrapper">
<?PHP if(isset($err_msg)) error_handling($err_msg, $err_lvl); ?>
			<div class="container-fluid">
				<div class="row">
					<div class="col-lg-12">
						<h1 class="page-header">
							<?php echo $lang['wihladm2']; ?>
						</h1>
					</div>
				</div>
				<form name="post" method="POST">
				<input type="hidden" name="csrf_token" value="<?PHP echo $_SESSION[$rspathhex.'csrf_token']; ?>">
				<div class="form-horizontal">
					<div class="row">
						<div class="col-md-3">
						</div>
						<div class="col-md-6">
							<div class="panel panel-default">
								<div class="panel-body">
									<div class="form-group">
										<label class="col-sm-4 control-label" data-toggle="modal" data-target="#wiadmhidedesc"><?php echo $lang['wiadmhide']; ?><i class="help-hover glyphicon glyphicon-question-sign"></i></label>
										<div class="col-sm-8 pull-right">
											<select class="selectpicker show-tick form-control" id="number" name="number" onchange="this.form.submit();">
											<?PHP
											echo '<option value="yes"'; if(!isset($_SESSION[$rspathhex.'showexcepted']) || $_SESSION[$rspathhex.'showexcepted'] == "yes") echo " selected=selected"; echo '>hide</option>';
											echo '<option value="no"'; if(isset($_SESSION[$rspathhex.'showexcepted']) && $_SESSION[$rspathhex.'showexcepted'] == "no") echo " selected=selected"; echo '>show</option>';
											?>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-4 control-label" data-toggle="modal" data-target="#wiselclddesc"><?php echo $lang['wiselcld']; ?><i class="help-hover glyphicon glyphicon-question-sign"></i></label>
										<div class="col-sm-8">
											<select class="selectpicker show-tick form-control" data-live-search="true" multiple name="user[]">
											<?PHP
											foreach ($user_arr as $user) {
												echo '<option value="',$user['uuid'],'" data-subtext="UUID: ',$user['uuid'],'; DBID: ',$user['cldbid'],'">',htmlspecialchars($user['name']),'</option>';
											}
											?>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-4 control-label" data-toggle="modal" data-target="#setontimedesc2"><?php echo $lang['setontime2']; ?><i class="help-hover glyphicon glyphicon-question-sign"></i></label>
										<div class="col-sm-8">
											<input type="text" class="form-control" name="setontime_day">
											<script>
											$("input[name='setontime_day']").TouchSpin({
												min: 0,
												max: 24855,
												verticalbuttons: true,
												prefix: '<?PHP echo $lang['time_day']; ?>'
											});
											</script>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-4 control-label" data-toggle="modal" data-target="#setontimedesc2"></label>
										<div class="col-sm-8">
											<input type="text" class="form-control" name="setontime_hour">
											<script>
											$("input[name='setontime_hour']").TouchSpin({
												min: 0,
												max: 23,
												verticalbuttons: true,
												prefix: '<?PHP echo $lang['time_hour']; ?>'
											});
											</script>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-4 control-label" data-toggle="modal" data-target="#setontimedesc2"></label>
										<div class="col-sm-8">
											<input type="text" class="form-control" name="setontime_min">
											<script>
											$("input[name='setontime_min']").TouchSpin({
												min: 0,
												max: 59,
												verticalbuttons: true,
												prefix: '<?PHP echo $lang['time_min']; ?>'
											});
											</script>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-4 control-label" data-toggle="modal" data-target="#setontimedesc2"></label>
										<div class="col-sm-8">
											<input type="text" class="form-control" name="setontime_sec">
											<script>
											$("input[name='setontime_sec']").TouchSpin({
												min: 0,
												max: 59,
												verticalbuttons: true,
												prefix: '<?PHP echo $lang['time_sec']; ?>'
											});
											</script>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">&nbsp;</div>
					<div class="row">
						<div class="text-center">
							<button type="submit" class="btn btn-primary" name="update"><?php echo $lang['wisvconf']; ?></button>
						</div>
					</div>
					<div class="row">&nbsp;</div>
				</div>
				</form>
			</div>
		</div>
	</div>
	
<div class="modal fade" id="wiselclddesc" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><?php echo $lang['wiselcld']; ?></h4>
      </div>
      <div class="modal-body">
        <?php echo $lang['wiselclddesc']; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?PHP echo $lang['stnv0002']; ?></button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="setontimedesc2" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><?php echo $lang['setontime2']; ?></h4>
      </div>
      <div class="modal-body">
        <?php echo $lang['setontimedesc2']; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?PHP echo $lang['stnv0002']; ?></button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="wiadmhidedesc" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><?php echo $lang['wiadmhide']; ?></h4>
      </div>
      <div class="modal-body">
        <?php echo $lang['wiadmhidedesc']; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?PHP echo $lang['stnv0002']; ?></button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
