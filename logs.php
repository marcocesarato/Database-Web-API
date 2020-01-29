<?php

session_start();

// TODO - Connection to database or something like
// TODO - CHANGE IT
$userinfo = array(
	'user1' => '0b14d501a594442a01c6859541bcb3e8164d183d32937b851835442f69d5c94e', // password1
	'user2' => '6cf615d5bcaac778352a8f1f3360d23f02f34ec182e259897fd6ce485d7870d4', // password2
);

if (isset($_POST['username'])) {
	$_POST['password'] = hash('sha256', $_POST['password']);
	if (@$userinfo[$_POST['username']] == $_POST['password']) {
		$_SESSION['username'] = $_POST['username'];
	}
}
if (!isset($_SESSION['username'])) {
	echo <<<EOD
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>API Logs - Login</title>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    </head>
    <body>
	    <div class="jumbotron text-center">
	        <h1 class="display-4">API</h1>
	        <h2>Logs - Login</h2>
	    </div>
	    <div class="container pb-4">
			<form action="#" method="POST">
				<div class="form-group">
					<label>Username</label>
					<input name="" class="form-control" placeholder="Username" type="username">
				</div>
					<label>Password</label>
					<input class="form-control" placeholder="******" type="password">
				</div>
				<div class="form-group"> 
				</div>
				<div class="form-group">
					<button type="submit" class="btn btn-primary btn-block">Login</button>
				</div>                                                       
			</form>
		</div>
    </body>
</html>
EOD;
}

echo <<<EOD
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>API Logs</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    
    <!-- Select picker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.11/css/bootstrap-select.min.css" crossorigin="anonymous" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.11/js/bootstrap-select.min.js" crossorigin="anonymous"></script>
    
    <!-- Datatable -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css" crossorigin="anonymous">
    <script src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>
    
    <script>
        $(document).ready(function() {
            $('#logs-table').DataTable();
        });
    </script>

</head>
<body>
    <div class="jumbotron text-center">
        <h1 class="display-4">API</h1>
        <h2>Logs</h2>
    </div>
    <div class="container-fluid pb-4">
EOD;

if (empty($_REQUEST['file'])) {
	$logs = scandir('logs');

	echo '<h3 class="mb-4">Logs list</h3>';

	echo '<ul class="list-group">';
	foreach ($logs as $log) {
		if (strrpos($log, '.log') !== false) {
			echo '<li class="list-group-item"><a href="?file=' . $log . '">' . $log . ' (' . (date('l', strtotime(str_replace(array('log-', '.log'), '', $log)))) . ')</a></li>';
		}
	}
	echo '</ul>';
} else {
	$_REQUEST['file'] = str_replace(array('/', '..'), '', $_REQUEST['file']);

	if (file_exists('logs/' . $_REQUEST['file'])) {
		include_once '../../config.php';
		$db = new PDO('pgsql:dbname=' . $dbconfig['db_name'] . ';port=' . $dbconfig['db_port'] . ';host=' . $dbconfig['db_host_name'], $dbconfig['db_user_name'], $dbconfig['db_password']);

		$f = file_get_contents('logs/' . $_REQUEST['file']);

		$type = 'new';
		if (strpos($f, "\n\n") !== false) {
			$f = str_replace(array("\n\n", "\n", "\n\n"), array('[br]', '', "\n"), $f);
			$type = 'old';
		}

		$re = '/^\[(.*?)\]\s\[(.*?)\]\s\[(?:method\s)?(.*?)\]\s\[(?:url\s)?(.*?)\](?:\s\[token\s(.*?)\])?(?:\s\[client\s(.*?)\])?:(?:[\s\t])(?:([a-zA-Z]+)(\s.*))?(.*)$/m';

		preg_match_all($re, $f, $matches, PREG_SET_ORDER, 0);

		$logs = array();
		$users = array();
		$log_types = array();
		$actions = array();
		foreach ($matches as $row) {
			list($full_row, $date, $log_type, $method, $url, $token, $ip, $action, $query, $message) = $row;

			if (!isset($logs[$token])) {
				$logs[$token] = array();

				if (!empty($token)) {
					$sth = $db->prepare("SELECT u.first_name || ' ' || u.last_name FROM api_authentication AS a INNER JOIN users AS u ON u.id = a.user_id WHERE a.token = :token");
					$sth->bindValue(':token', $token, PDO::PARAM_STR);
					if ($sth->execute()) {
						$name = $sth->fetch(PDO::FETCH_COLUMN);
					} else {
						$name = $token;
					}

					if (!isset($users[$name])) {
						$users[$name] = array();
					}
					$users[$name][] = $token;
				} elseif (!isset($users['Vuoto'])) {
					$users['Vuoto'] = array('-empty-');
				}
			}
			if (!isset($logs[$token][$log_type])) {
				$logs[$token][$log_type] = array();
				if (!in_array($log_type, $log_types)) {
					$log_types[] = $log_type;
				}
			}

			$action_group = (empty($action) ? 'MESSAGE' : $action);
			if (!isset($logs[$token][$log_type][$action_group])) {
				$logs[$token][$log_type][$action_group] = array();
				if (!in_array($action_group, $actions)) {
					$actions[] = $action_group;
				}
			}

			$log = array(
				'date'   => $date,
				'url'    => $url,
				'method' => $method,
			);

			if (!empty($action)) {
				$log['query'] = $action . $query;
			} else {
				$log['message'] = $message;
			}

			$logs[$token][$log_type][$action_group][] = $log;
		}

		echo '<h3>Select log to view</h3>';
		echo '<form method="POST">';
		echo '<div class="d-inline mr-2">';
		echo '<label for="user" >User: <select class="selectpicker form-control" name="user"><option value="">Tutti</option>';
		foreach ($users as $user => $token) {
			$token = empty($token) ? '-empty-' : implode(',', $token);
			echo '<option value="' . $token . '"' . (isset($_REQUEST['user']) && $_REQUEST['user'] == $token ? ' selected' : '') . '>' . $user . '</option>';
		}
		echo '</select></label></div>';

		echo '<div class="d-inline mr-2">';
		echo '<label for="type">Log type: <select class="selectpicker form-control" name="type"><option value="">Tutti</option>';
		foreach ($log_types as $log_type) {
			echo '<option' . (isset($_REQUEST['type']) && $_REQUEST['type'] == $log_type ? ' selected' : '') . '>' . $log_type . '</option>';
		}
		echo '</select></label></div>';

		echo '<div class="d-inline mr-2">';
		echo '<label for="action">Action: <select class="selectpicker form-control" name="action"><option value="">Tutte</option>';
		foreach ($actions as $action) {
			echo '<option' . (isset($_REQUEST['action']) && $_REQUEST['action'] == $action ? ' selected' : '') . '>' . $action . '</option>';
		}
		echo '</select></label></div>';

		echo '<div class="d-inline">';
		echo '<input class="btn btn-primary" type="submit" value="Visualizza" name="show" />';
		echo '</div>';
		echo '</form>';

		if (isset($_REQUEST['show'])) {
			$user = explode(',', $_REQUEST['user']);
			$log_type = $_REQUEST['type'];
			$action = $_REQUEST['action'];

			$users_new = array();
			foreach ($users as $username => $u) {
				foreach ($u as $t) {
					$users_new[$t] = $username;
				}
			}
			$users = $users_new;

			$users_db = array();
			$sth = $db->prepare("SELECT id, first_name || ' ' || last_name AS name FROM users");
			if ($sth->execute()) {
				$users_db = $sth->fetchAll(PDO::FETCH_ASSOC);
			}

			$output = array();
			foreach ($logs as $t => $log) {
				$t = empty($t) ? '-empty-' : $t;
				if (empty($user[0]) || in_array($t, $user)) {
					foreach ($log as $lt => $types) {
						if (empty($log_type) || ($lt === $log_type)) {
							foreach ($types as $a => $actions) {
								if (empty($action) || ($a === $action)) {
									$u = (isset($users[$t]) ? $users[$t] : '');

									foreach ($actions as $def) {
										if (isset($def['message']) && in_array(substr($def['message'], 0, 1), array('{', '['))) {
											$msg = json_decode($def['message'], true);

											if (isset($msg['user'])) {
												$key = array_search($msg['user']['id'], array_column($users_db, 'id'));
												if (!empty($key)) {
													$u = $users_db[$key]['name'];
												}
											}
										}

										$output_date = strtotime($def['date']);
										while (isset($output[$output_date])) {
											$output_date++;
										}
										$output[$output_date] = '<tr class="' . strtolower($lt) . '"><td>' . $def['date'] . '</td><td>' . $u . '</td>' . (empty($log_type) ? '<td>' . $lt . '</td>' : '') . '<td>' . (isset($def['query']) ? $def['query'] : $def['message']) . '</td><td>' . $def['method'] . '</td><td>' . $def['url'] . '</td></tr>';
									}
								}
							}
						}
					}
				}
			}
			echo '<h4>Results</h4>';
			echo '<table class="table" id="logs-table"><thead><tr><th scope="col">Date</th><th scope="col">User</th>' . (empty($log_type) ? '<th scope="col">Type</th>' : '') . '<th scope="col">Query / Message</th><th scope="col">Method</th><th scope="col">URL</th></tr></thead><tbody>';
			ksort($output, SORT_NUMERIC);
			echo implode('', $output);
			echo '</tbody></table>';
			echo '<style>
                table th{ background-color: #333; color: #fff; font-weight: bold; }
                table td{ border: 0; }
                table tr:not(:last-child) td{ border-bottom: 1px solid gray; }
                table tr:nth-child(odd) td{ background-color: #f6f6f6; }
                table tr:nth-child(even) td{ background-color: #ccc; }
                
                table tr.debug:nth-child(odd) td{ background-color: #d9daea; }
                table tr.debug:nth-child(even) td{ background-color: #bbbcd4; }
                table tr.error:nth-child(odd) td{ background-color: #f3d4d4; }
                table tr.error:nth-child(even) td{ background-color: #f3bdbe; }
            </style>';
		}
	} else {
		echo 'File not found.';
	}
}

echo <<<EOD
    </div>
</body>
</html>
EOD;
