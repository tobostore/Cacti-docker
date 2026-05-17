<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* set default action */
if (isset($_REQUEST["action"])) {
	$action = $_REQUEST["action"];
}else{
	$action = "";
}

/* Get the username */
if (read_config_option("auth_method") == "2") {
	/* Get the Web Basic Auth username and set action so we login right away */
	$action = "login";
	if (isset($_SERVER["PHP_AUTH_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["PHP_AUTH_USER"]);
	}elseif (isset($_SERVER["REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["REMOTE_USER"]);
	}elseif (isset($_SERVER["REDIRECT_REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["REDIRECT_REMOTE_USER"]);
	}elseif (isset($_SERVER["HTTP_PHP_AUTH_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["HTTP_PHP_AUTH_USER"]);
	}elseif (isset($_SERVER["HTTP_REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["HTTP_REMOTE_USER"]);
	}elseif (isset($_SERVER["HTTP_REDIRECT_REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["HTTP_REDIRECT_REMOTE_USER"]);

	}else{
		/* No user - Bad juju! */
		$username = "";
		cacti_log("ERROR: No username passed with Web Basic Authentication enabled.", false, "AUTH");
		auth_display_custom_error_message("Web Basic Authentication configured, but no username was passed from the web server.  Please make sure you have authentication enabled on the web server.");
		exit;
	}
}else{
	if ($action == "login") {
		/* LDAP and Builtin get username from Form */
		$username = get_request_var_post("login_username");
	}else{
		$username = "";
	}
}

$username = sanitize_search_string($username);

/* process login */
$copy_user = false;
$user_auth = false;
$user_enabled = 1;
$ldap_error = false;
$ldap_error_message = "";
$realm = 0;
if ($action == 'login') {
	switch (read_config_option("auth_method")) {
	case "0":
		/* No auth, no action, also shouldn't get here */
		exit;

		break;
	case "2":
		/* Web Basic Auth */
		$copy_user = true;
		$user_auth = true;
		$realm = 2;
		/* Locate user in database */
		$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND realm = 2");

		if (!$user && read_config_option('user_template') == '0' && read_config_option('guest_user') == '0') {
			cacti_log("ERROR: User '" . $username . "' authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.  Exiting.", false, 'AUTH');

			$username = htmlspecialchars($username);
			auth_display_custom_error_message("$username authenticated by Web Server, but a Template User and a Guest User are not defined in Cacti.");
			exit;			
		}

		break;
	case "3":
		/* LDAP Auth */
 		if ((get_request_var_post("realm") == "ldap") && (strlen(get_request_var_post("login_password")) > 0)) {
			/* include LDAP lib */
			include_once("./lib/ldap.php");

			/* get user DN */
			$ldap_dn_search_response = cacti_ldap_search_dn($username);
			if ($ldap_dn_search_response["error_num"] == "0") {
				$ldap_dn = $ldap_dn_search_response["dn"];
			}else{
				/* Error searching */
				cacti_log("LOGIN: LDAP Error: " . $ldap_dn_search_response["error_text"], false, "AUTH");
				$ldap_error = true;
				$ldap_error_message = "LDAP Search Error: " . $ldap_dn_search_response["error_text"];
				$user_auth = false;
				$user = array();
			}

			if (!$ldap_error) {
				/* auth user with LDAP */
				$ldap_auth_response = cacti_ldap_auth($username,stripslashes(get_request_var_post("login_password")),$ldap_dn);

				if ($ldap_auth_response["error_num"] == "0") {
					/* User ok */
					$user_auth = true;
					$copy_user = true;
					$realm = 1;
					/* Locate user in database */
					cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated", false, "AUTH");
					$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND realm = 1");
				}else{
					/* error */
					cacti_log("LOGIN: LDAP Error: " . $ldap_auth_response["error_text"], false, "AUTH");
					$ldap_error = true;
					$ldap_error_message = "LDAP Error: " . $ldap_auth_response["error_text"];
					$user_auth = false;
					$user = array();
				}
			}

		}

	default:
		if (!api_plugin_hook_function('login_process', false)) {
			/* Builtin Auth */
			if ((!$user_auth) && (!$ldap_error)) {
				/* if auth has not occured process for builtin - AKA Ldap fall through */
				$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND password = '" . md5(get_request_var_post("login_password")) . "' AND realm = 0");
			}
		}
	}
	/* end of switch */

	/* Create user from template if requested */
	if ((!sizeof($user)) && ($copy_user) && (read_config_option("user_template") != "0") && (strlen($username) > 0)) {
		cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, "AUTH");
		/* check that template user exists */
		if (db_fetch_row("SELECT id FROM user_auth WHERE username = '" . read_config_option("user_template") . "' AND realm = 0")) {
			/* template user found */
			user_copy(read_config_option("user_template"), $username, 0, $realm);
			/* requery newly created user */
			$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND realm = " . $realm);
		}else{
			/* error */
			cacti_log("LOGIN: Template user '" . read_config_option("user_template") . "' does not exist.", false, "AUTH");
			auth_display_custom_error_message("Template user '" . read_config_option("user_template") . "' does not exist.");
			exit;
		}
	}

	/* Guest account checking - Not for builtin */
	$guest_user = false;
	if ((sizeof($user) < 1) && ($user_auth) && (read_config_option("guest_user") != "0")) {
		/* Locate guest user record */
		$user = db_fetch_row("SELECT * FROM user_auth WHERE username = '" . read_config_option("guest_user") . "'");
		if ($user) {
			cacti_log("LOGIN: Authenicated user '" . $username . "' using guest account '" . $user["username"] . "'", false, "AUTH");
			$guest_user = true;
		}else{
			/* error */
			auth_display_custom_error_message("Guest user \"" . read_config_option("guest_user") . "\" does not exist.");
			cacti_log("LOGIN: Unable to locate guest user '" . read_config_option("guest_user") . "'", false, "AUTH");
			exit;
		}
	}

	/* Process the user  */
	if (sizeof($user) > 0) {
		cacti_log("LOGIN: User '" . $user["username"] . "' Authenticated", false, "AUTH");
		db_execute("INSERT INTO user_log (username,user_id,result,ip,time) VALUES (" . $cnn_id->qstr($username) . "," . $user["id"] . ",1,'" . $_SERVER["REMOTE_ADDR"] . "',NOW())");
		/* is user enabled */
		$user_enabled = $user["enabled"];
		if ($user_enabled != "on") {
			/* Display error */
			auth_display_custom_error_message("Access Denied, user account disabled.");
			exit;
		}

		/* set the php session */
		$_SESSION["sess_user_id"] = $user["id"];

		/* handle "force change password" */
		if (($user["must_change_password"] == "on") && (read_config_option("auth_method") == 1)) {
			$_SESSION["sess_change_password"] = true;
		}

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user["login_opts"]) {
			case '1': /* referer */
				/* because we use plugins, we can't redirect back to graph_view.php if they don't
				 * have console access
				 */
				if (isset($_SERVER["HTTP_REFERER"])) {
					$referer = $_SERVER["HTTP_REFERER"];
					if (basename($referer) == "logout.php") {
						$referer = $config['url_path'] . "index.php";
					}
				} else if (isset($_SERVER["REQUEST_URI"])) {
					$referer = $_SERVER["REQUEST_URI"];
					if (basename($referer) == "logout.php") {
						$referer = $config['url_path'] . "index.php";
					}
				} else {
					$referer = $config['url_path'] . "index.php";
				}

				if (substr_count($referer, "plugins")) {
					header("Location: " . $referer);
				} elseif (sizeof(db_fetch_assoc("SELECT realm_id FROM user_auth_realm WHERE realm_id = 8 AND user_id = " . $_SESSION["sess_user_id"])) == 0) {
					header("Location: graph_view.php");
				} else {
					header("Location: $referer");
				}

				break;
			case '2': /* default console page */
				header("Location: " . $config['url_path'] . "index.php");

				break;
			case '3': /* default graph page */
				header("Location: " . $config['url_path'] . "graph_view.php");

				break;
			default:
				api_plugin_hook_function('login_options_navigate', $user['login_opts']);
		}
		exit;
	}else{
		if ((!$guest_user) && ($user_auth)) {
			/* No guest account defined */
			auth_display_custom_error_message("Access Denied, please contact you Cacti Administrator.");
			cacti_log("LOGIN: Access Denied, No guest enabled or template user to copy", false, "AUTH");
			exit;
		}else{
			/* BAD username/password builtin and LDAP */
			db_execute("INSERT INTO user_log (username,user_id,result,ip,time) VALUES (" . $cnn_id->qstr($username) . ",0,0,'" . $_SERVER["REMOTE_ADDR"] . "',NOW())");
		}
	}
}

/* auth_display_custom_error_message - displays a custom error message to the browser that looks like
     the pre-defined error messages
   @arg $message - the actual text of the error message to display */
function auth_display_custom_error_message($message) {
	/* kill the session */
	setcookie(session_name(),"",time() - 3600,"/");
	/* print error */
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">";
	print "<html>\n<head>\n";
	print "     <title>" . "Cacti" . "</title>\n";
	print "     <meta http-equiv='Content-Type' content='text/html;charset=utf-8'>";
	print "     <link href=\"include/main.css\" type=\"text/css\" rel=\"stylesheet\">";
	print "</head>\n";
	print "<body>\n<br><br>\n";
	display_custom_error_message($message);
	print "</body>\n</html>\n";
}

if (api_plugin_hook_function('custom_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}
?>
<!DOCTYPE HTML>
<html lang="id">
<head>
		<title><?php print api_plugin_hook_function("login_title", "Cacti | Network Monitor");?></title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style type="text/css">
			@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

		:root {
				--primary: #61e6c6;
				--secondary: #66a6ff;
				--accent: #f6c36b;
				--dark: #08111f;
				--darker: #040816;
				--card: rgba(10, 18, 35, 0.82);
				--card-border: rgba(255, 255, 255, 0.12);
				--text: #e8eefc;
				--muted: rgba(232, 238, 252, 0.7);
				--error: #ff6b7a;
				--shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
		}
		
			* { margin: 0; padding: 0; box-sizing: border-box; }

			body, button, input, select {
				font-family: 'Inter', 'Segoe UI', Verdana, Arial, sans-serif;
			}
		
		body {
				background:
					radial-gradient(circle at top left, rgba(102, 166, 255, 0.18), transparent 34%),
					radial-gradient(circle at bottom right, rgba(97, 230, 198, 0.16), transparent 28%),
					linear-gradient(135deg, #030712 0%, #08111f 42%, #050816 100%);
			color: var(--text);
			min-height: 100vh;
			overflow-x: hidden;
			position: relative;
		}
		
		.network-bg {
			position: fixed;
			top: 0; left: 0;
			width: 100%; height: 100%;
			z-index: -2;
				opacity: 0.85;
			}

			.network-bg::before,
			.network-bg::after {
				content: '';
				position: absolute;
				inset: 0;
				pointer-events: none;
			}

			.network-bg::before {
				background-image:
					linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
					linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
				background-size: 42px 42px;
				mask-image: radial-gradient(circle at center, black 35%, transparent 82%);
				opacity: 0.35;
			}

			.network-bg::after {
				background: radial-gradient(circle at center, transparent 0%, rgba(4, 8, 22, 0.25) 72%, rgba(4, 8, 22, 0.6) 100%);
		}
		
		.network-nodes {
			position: fixed;
			top: 0; left: 0;
			width: 100%; height: 100%;
			z-index: -1;
			pointer-events: none;
		}
		
		.node {
			position: absolute;
				width: 6px; height: 6px;
				background: linear-gradient(135deg, var(--primary), var(--secondary));
			border-radius: 50%;
				box-shadow: 0 0 16px rgba(97, 230, 198, 0.55);
				animation: float 10s infinite ease-in-out;
			opacity: 0.7;
		}
		
		.node::after {
			content: '';
			position: absolute;
			top: 50%; left: 50%;
			transform: translate(-50%, -50%);
				width: 26px; height: 26px;
				border: 1px solid rgba(97, 230, 198, 0.22);
			border-radius: 50%;
			animation: pulse 3s infinite;
		}
		
		@keyframes float {
			0%, 100% { transform: translateY(0) translateX(0); }
			25% { transform: translateY(-20px) translateX(10px); }
			50% { transform: translateY(10px) translateX(-15px); }
			75% { transform: translateY(-10px) translateX(5px); }
		}
		
		@keyframes pulse {
			0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.5; }
			50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.2; }
			100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.5; }
		}
		
		/* Connection Lines */
		.connection {
			position: absolute;
			height: 1px;
				background: linear-gradient(90deg, transparent, rgba(102, 166, 255, 0.4), transparent);
			transform-origin: left center;
			animation: dataFlow 4s infinite linear;
		}
		
		@keyframes dataFlow {
			0% { background-position: -200px 0; }
			100% { background-position: 200px 0; }
		}
		
		/* Login Container */
		.login-wrapper {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
				padding: 28px 18px;
			position: relative;
			z-index: 10;
		}
		
		.login-card {
				background: linear-gradient(180deg, rgba(17, 27, 48, 0.88), rgba(10, 18, 35, 0.96));
				border: 1px solid var(--card-border);
				border-radius: 24px;
				padding: 30px;
			width: 100%;
				max-width: 520px;
				box-shadow: var(--shadow);
			backdrop-filter: blur(10px);
				-webkit-backdrop-filter: blur(10px);
			position: relative;
			overflow: hidden;
		}
		
		.login-card::before {
			content: '';
			position: absolute;
			top: 0; left: 0; right: 0;
				height: 4px;
				background: linear-gradient(90deg, var(--secondary), var(--primary), var(--accent), var(--secondary));
			animation: gradientFlow 3s linear infinite;
			background-size: 200% 100%;
		}
		
		@keyframes gradientFlow {
			0% { background-position: 0% 50%; }
			100% { background-position: 200% 50%; }
		}
		
		/* Logo & Header */
		.login-header {
			text-align: center;
				margin-bottom: 22px;
				padding-bottom: 18px;
				border-bottom: 1px solid rgba(255, 255, 255, 0.08);
		}
		
		.login-logo {
			max-width: 200px;
			margin-bottom: 15px;
				filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.35));
			transition: transform 0.3s ease;
		}
		
		.login-logo:hover {
			transform: scale(1.05);
		}
		
		.login-title {
				font-size: 1.5rem;
				font-weight: 800;
				letter-spacing: -0.03em;
				color: #ffffff;
				margin-bottom: 8px;
		}
		
		.login-subtitle {
				font-size: 0.94rem;
				color: var(--muted);
				line-height: 1.5;
			}

			.login-badge-row {
				display: flex;
				flex-wrap: wrap;
				justify-content: center;
				gap: 8px;
				margin-top: 14px;
			}

			.login-badge {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 12px;
				border-radius: 999px;
				background: rgba(255, 255, 255, 0.05);
				border: 1px solid rgba(255, 255, 255, 0.08);
				color: var(--muted);
				font-size: 0.82rem;
		}
		
		/* Error Messages */
		.error-box {
				background: rgba(255, 107, 122, 0.12);
				border: 1px solid rgba(255, 107, 122, 0.2);
				padding: 12px 15px;
				margin: 14px 0;
				border-radius: 12px;
			font-size: 0.9rem;
				color: #ffd0d5;
		}
		
		/* Form Styling */
		.form-group {
				margin-bottom: 18px;
				transition: transform 0.2s ease;
		}
		
		.form-label {
			display: block;
			margin-bottom: 8px;
				font-weight: 600;
			color: var(--text);
			font-size: 0.95rem;
		}
		
		.form-input {
			width: 100%;
				padding: 13px 15px;
				background: rgba(6, 12, 24, 0.78);
				border: 1px solid rgba(255, 255, 255, 0.08);
				border-radius: 14px;
			color: var(--text);
			font-size: 1rem;
			transition: all 0.3s ease;
		}
		
		.form-input:focus {
			outline: none;
				border-color: rgba(97, 230, 198, 0.55);
				box-shadow: 0 0 0 4px rgba(97, 230, 198, 0.14);
				background: rgba(6, 12, 24, 0.95);
		}
		
		.form-input::placeholder {
				color: rgba(232, 238, 252, 0.42);
		}
		
		/* Realm Select */
		.form-select {
			width: 100%;
				padding: 13px 15px;
				background: rgba(6, 12, 24, 0.78);
				border: 1px solid rgba(255, 255, 255, 0.08);
				border-radius: 14px;
			color: var(--text);
			font-size: 1rem;
			cursor: pointer;
			transition: all 0.3s ease;
		}
		
		.form-select:focus {
			outline: none;
				border-color: rgba(102, 166, 255, 0.55);
				box-shadow: 0 0 0 4px rgba(102, 166, 255, 0.14);
		}
		
		.form-select option {
			background: var(--dark);
			color: var(--text);
		}
		
		/* Login Button */
		.login-btn {
			width: 100%;
				padding: 14px;
				background: linear-gradient(135deg, var(--secondary), var(--primary), var(--accent));
			border: none;
				border-radius: 14px;
			color: white;
			font-size: 1.1rem;
				font-weight: 700;
				letter-spacing: 0.02em;
			cursor: pointer;
			transition: all 0.3s ease;
			position: relative;
			overflow: hidden;
				box-shadow: 0 14px 28px rgba(102, 166, 255, 0.22);
		}
		
		.login-btn:hover {
			transform: translateY(-2px);
				box-shadow: 0 18px 34px rgba(97, 230, 198, 0.26);
		}
		
		.login-btn:active {
			transform: translateY(0);
		}
		
		.login-btn::after {
			content: '';
			position: absolute;
			top: -50%; left: -50%;
			width: 200%; height: 200%;
			background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
			transform: rotate(45deg);
			transition: 0.5s;
			opacity: 0;
		}
		
		.login-btn:hover::after {
			opacity: 1;
			left: 100%;
		}
		
		/* Footer Info */
		.login-footer {
				margin-top: 22px;
				padding-top: 18px;
				border-top: 1px solid rgba(255, 255, 255, 0.08);
			text-align: center;
			font-size: 0.85rem;
				color: var(--muted);
			line-height: 1.6;
		}
		
		.login-footer strong {
				color: #ffffff;
			display: block;
			margin-bottom: 5px;
				font-size: 0.98rem;
		}
		
		.login-footer a {
			color: var(--secondary);
			text-decoration: none;
		}
		
		.login-footer a:hover {
			text-decoration: underline;
		}
		
		/* Network Icon Decorations */
		.network-icon {
			position: absolute;
			font-size: 2rem;
				opacity: 0.08;
			z-index: 0;
			pointer-events: none;
		}
		
		.icon-1 { top: 10%; right: 10%; animation: float 6s infinite; }
		.icon-2 { bottom: 15%; left: 8%; animation: float 7s infinite reverse; }
		.icon-3 { top: 20%; left: 5%; animation: float 9s infinite; }
		
		/* Responsive */
		@media (max-width: 520px) {
			.login-card {
					padding: 24px 18px;
					margin: 8px;
					border-radius: 20px;
			}
			.login-title {
					font-size: 1.15rem;
			}
			.form-input, .form-select {
					padding: 11px 12px;
				font-size: 0.95rem;
			}
			.login-btn {
				padding: 12px;
				font-size: 1rem;
			}
		}
		
		/* Focus visible for accessibility */
		:focus-visible {
				outline: 2px solid var(--accent);
			outline-offset: 2px;
		}
	</style>
</head>
<body>
	<!-- Network Background Elements -->
	<div class="network-bg"></div>
	<div class="network-nodes" id="networkNodes"></div>
	
	<!-- Decorative Network Icons -->
	<div class="network-icon icon-1">🌐</div>
	<div class="network-icon icon-2">🔗</div>
	<div class="network-icon icon-3">📡</div>
	
	<div class="login-wrapper">
		<form name="login" method="post" action="<?php print basename($_SERVER["PHP_SELF"]);?>" class="login-card">
			<input type="hidden" name="action" value="login">
			
			<?php
			api_plugin_hook_function("login_before", array('ldap_error' => $ldap_error, 'ldap_error_message' => $ldap_error_message, 'username' => $username, 'user_enabled' => $user_enabled, 'action' => $action));
			
			$cacti_logo = $config['url_path'] . 'images/auth_login.gif';
			$cacti_logo = api_plugin_hook_function('cacti_image', $cacti_logo);
			?>
			
			<div class="login-header">
				<?php if ($cacti_logo != '') { ?>
					<img src="<?php echo $cacti_logo; ?>" class="login-logo" alt="Cacti Network Monitor">
				<?php } ?>
				<div class="login-title">Network Monitoring Portal</div>
				<div class="login-subtitle">Silakan masuk untuk mengakses dashboard monitoring.</div>
				<div class="login-badge-row">
					<span class="login-badge">Secure Login</span>
					<span class="login-badge">Cacti Monitoring</span>
				</div>
			</div>
			
			<?php if ($ldap_error) { ?>
				<div class="error-box">⚠️ <?php print $ldap_error_message; ?></div>
			<?php } elseif ($action == "login") { ?>
				<div class="error-box">❌ Invalid User Name/Password. Please retry.</div>
			<?php } ?>
			
			<?php if ($user_enabled == "0") { ?>
				<div class="error-box">🔒 User Account Disabled. Contact Administrator.</div>
			<?php } ?>
			
			<div style="margin: 15px 0 18px; padding: 14px 15px; background: rgba(97, 230, 198, 0.08); border-radius: 14px; border: 1px solid rgba(97, 230, 198, 0.14);">
				<small style="color: var(--primary); font-weight: 600;">🔐 Masukkan username dan password yang sudah diberikan untuk akses monitoring.</small>
			</div>
			
			<div class="form-group">
				<label class="form-label" for="login_username">👤 User Name</label>
				<input type="text" id="login_username" name="login_username" class="form-input" 
					   value="<?php print htmlspecialchars($username, ENT_QUOTES); ?>" 
					   placeholder="Enter your network username" autocomplete="username" required>
			</div>
			
			<div class="form-group">
				<label class="form-label" for="login_password">🔑 Password</label>
				<input type="password" id="login_password" name="login_password" class="form-input" 
					   placeholder="Enter your secure password" autocomplete="current-password" required>
			</div>
			
			<?php if (read_config_option("auth_method") == "3" || api_plugin_hook_function('login_realms_exist')) {
				$realms = api_plugin_hook_function('login_realms', array("local" => array("name" => "Local", "selected" => false), "ldap" => array("name" => "LDAP", "selected" => true)));
			?>
			<div class="form-group">
				<label class="form-label" for="realm">🌍 Authentication Realm</label>
				<select name="realm" id="realm" class="form-select">
					<?php if (sizeof($realms)) {
					foreach($realms as $name => $realm) {
						print "\t\t\t\t\t<option value='" . $name . "'" . ($realm["selected"] ? " selected":"") . ">" . htmlspecialchars($realm["name"], ENT_QUOTES) . "</option>\n";
					}
					} ?>
				</select>
			</div>
			<?php } ?>
			
			<button type="submit" class="login-btn">
				🚀 CONNECT TO DASHBOARD 
			</button>
			
			<div class="login-footer">
				<strong>Network Operations Center</strong>
				<small style="opacity: 0.75;">© <?php echo date('Y'); ?> Secure access only</small>
			</div>
			
			<?php api_plugin_hook('login_after'); ?>
		</form>
	</div>
	
	<script>
		// Generate animated network nodes
		document.addEventListener('DOMContentLoaded', function() {
			const container = document.getElementById('networkNodes');
			const nodeCount = 25;
			
			for (let i = 0; i < nodeCount; i++) {
				const node = document.createElement('div');
				node.className = 'node';
				node.style.left = Math.random() * 100 + '%';
				node.style.top = Math.random() * 100 + '%';
				node.style.animationDelay = Math.random() * 8 + 's';
				node.style.animationDuration = (6 + Math.random() * 4) + 's';
				container.appendChild(node);
				
				// Occasionally create connection lines between nodes
				if (i % 3 === 0 && i > 0) {
					const line = document.createElement('div');
					line.className = 'connection';
					line.style.width = Math.random() * 300 + 100 + 'px';
					line.style.left = Math.random() * 80 + '%';
					line.style.top = Math.random() * 80 + '%';
					line.style.transform = `rotate(${Math.random() * 360}deg)`;
					line.style.animationDelay = Math.random() * 4 + 's';
					container.appendChild(line);
				}
			}
			
			// Focus username field
			const usernameField = document.querySelector('input[name="login_username"]');
			if (usernameField) usernameField.focus();
		});
		
		// Add subtle hover effect to form inputs
		document.querySelectorAll('.form-input, .form-select').forEach(input => {
			input.addEventListener('focus', function() {
				this.parentElement.style.transform = 'scale(1.02)';
				this.parentElement.style.transition = 'transform 0.2s ease';
			});
			input.addEventListener('blur', function() {
				this.parentElement.style.transform = 'scale(1)';
			});
		});
	</script>
</body>
</html>
