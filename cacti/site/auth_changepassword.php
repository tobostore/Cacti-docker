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

include("./include/global.php");

/* find out if we are logged in as a 'guest user' or not, if we are redirect away from password change */
if (db_fetch_cell("select id from user_auth where username='" . read_config_option("guest_user") . "'") == $_SESSION["sess_user_id"]) {
	header("Location: index.php");
}

$user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);

/* default to !bad_password */
$bad_password = false;

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'changepassword':
	if (($_POST["password"] == $_POST["confirm"]) && ($_POST["password"] != "")) {
		db_execute("insert into user_log (username,result,ip) values('" . $user["username"] . "',3,'" . $_SERVER["REMOTE_ADDR"] . "')");
		db_execute("update user_auth set must_change_password='',password='" . md5($_POST["password"]) . "' where id=" . $_SESSION["sess_user_id"]);

		kill_session_var("sess_change_password");

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */

		/* if no console permissions show graphs otherwise, pay attention to user setting */
		$realm_id = $user_auth_realm_filenames["index.php"];

		if (sizeof(db_fetch_assoc("select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id = '" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id = '" . $realm_id . "'")) > 0) {
			switch ($user["login_opts"]) {
				case '1': /* referer */
					header("Location: " . sanitize_uri($_POST["ref"])); break;
				case '2': /* default console page */
					header("Location: index.php"); break;
				case '3': /* default graph page */
					header("Location: graph_view.php"); break;
				default:
					api_plugin_hook_function('login_options_navigate', $user['login_opts']);
			}
		}else{
			header("Location: graph_view.php");
		}
		exit;

	}else{
		$bad_password = true;
	}

	break;
}

if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	exit;
}

?>
<!DOCTYPE HTML>
<html lang="id">
<head>
	<title><?php print api_plugin_hook_function("login_title", "Cacti | Change Password");?></title>
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

		.error-box {
			background: rgba(255, 107, 122, 0.12);
			border: 1px solid rgba(255, 107, 122, 0.2);
			padding: 12px 15px;
			margin: 14px 0;
			border-radius: 12px;
			font-size: 0.9rem;
			color: #ffd0d5;
		}

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

		.login-btn {
			width: 100%;
			padding: 14px;
			background: linear-gradient(135deg, var(--secondary), var(--primary), var(--accent));
			border: none;
			border-radius: 14px;
			color: white;
			font-size: 1.05rem;
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

		.login-footer small {
			color: rgba(232, 238, 252, 0.7);
		}

		@media (max-width: 520px) {
			.login-card {
				padding: 24px 18px;
				margin: 8px;
				border-radius: 20px;
			}

			.login-title {
				font-size: 1.15rem;
			}

			.form-input {
				padding: 11px 12px;
				font-size: 0.95rem;
			}

			.login-btn {
				padding: 12px;
				font-size: 1rem;
			}
		}

		:focus-visible {
			outline: 2px solid var(--accent);
			outline-offset: 2px;
		}
	</style>
</head>
<body>
	<div class="network-bg"></div>
	<div class="network-nodes" id="networkNodes"></div>

	<div class="login-wrapper">
		<form name="login" method="post" action="<?php print basename($_SERVER["PHP_SELF"]);?>" class="login-card">
			<input type="hidden" name="action" value="changepassword">
			<input type="hidden" name="ref" value="<?php print (isset($_REQUEST["ref"]) ? sanitize_uri($_REQUEST["ref"]) : '');?>">

			<div class="login-header">
				<div class="login-title">Change Password</div>
				<div class="login-subtitle">Akun Anda mewajibkan penggantian password sebelum masuk ke dashboard.</div>
				<div class="login-badge-row">
					<span class="login-badge">Secure Update</span>
					<span class="login-badge">Cacti Access</span>
					<span class="login-badge">Protected Session</span>
				</div>
			</div>

			<?php if ($bad_password == true) {?>
				<div class="error-box">Your passwords do not match. Please retype both fields.</div>
			<?php }?>

			<div class="error-box">Forced password change required. Enter a new password to continue.</div>

			<div class="form-group">
				<label class="form-label" for="password">New Password</label>
				<input type="password" id="password" name="password" class="form-input" placeholder="Enter new password" required>
			</div>

			<div class="form-group">
				<label class="form-label" for="confirm">Confirm Password</label>
				<input type="password" id="confirm" name="confirm" class="form-input" placeholder="Retype new password" required>
			</div>

			<button type="submit" class="login-btn">Save New Password</button>

			<div class="login-footer">
				<strong>Network Operations Center</strong>
				<small>© <?php echo date('Y'); ?> Secure access only</small>
			</div>
		</form>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const container = document.getElementById('networkNodes');
			const nodeCount = 22;

			for (let i = 0; i < nodeCount; i++) {
				const node = document.createElement('div');
				node.className = 'node';
				node.style.left = Math.random() * 100 + '%';
				node.style.top = Math.random() * 100 + '%';
				node.style.animationDelay = Math.random() * 8 + 's';
				node.style.animationDuration = (6 + Math.random() * 4) + 's';
				container.appendChild(node);

				if (i % 3 === 0 && i > 0) {
					const line = document.createElement('div');
					line.className = 'connection';
					line.style.width = Math.random() * 260 + 120 + 'px';
					line.style.left = Math.random() * 80 + '%';
					line.style.top = Math.random() * 80 + '%';
					line.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
					line.style.animationDelay = Math.random() * 4 + 's';
					container.appendChild(line);
				}
			}

			const passwordField = document.getElementById('password');
			if (passwordField) passwordField.focus();

			document.querySelectorAll('.form-input').forEach(input => {
				input.addEventListener('focus', function() {
					this.parentElement.style.transform = 'scale(1.01)';
				});
				input.addEventListener('blur', function() {
					this.parentElement.style.transform = 'scale(1)';
				});
			});
		});
	</script>
</body>
</html>
