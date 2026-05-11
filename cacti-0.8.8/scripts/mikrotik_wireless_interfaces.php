<?php
	$no_http_headers = true;
	error_reporting(0);
	snmp_set_oid_numeric_print(TRUE);
	
	// convert integer oid string to mac address
	function oidtomac($oid) {
		$mac       = "";
		$oid_split = explode(".", substr($oid, 1));
		
		for ($x=0; $x<count($oid_split); $x++) {
			$octet = strtoupper(dechex($oid_split[$x]));
			$mac  .= ":";
			
			if (strlen($octet) == 1)
				$mac .= "0";
			
			$mac  .= $octet;
		}
		
		return substr($mac, 1);  
	}
	
	// convert mac address to integer oid string
	function mactooid($mac) {
		$oid       = "";
		$mac_split = explode(":", $mac);
		
		for ($x=0; $x<count($mac_split); $x++) {
			$octet = hexdec($mac_split[$x]);
			$oid  .= ".";
			$oid  .= $octet;
		}
		
		return $oid;  
	}
	
	// wireless registration table oid
	$oid_w_tbl = ".1.3.6.1.4.1.14988.1.1.1.2.1.";
	$oid_w_['ifInSignal']   = "3";
	$oid_w_['ifOutOctets']  = "4";
	$oid_w_['ifInOctets']   = "5";
	$oid_w_['ifOutPackets'] = "6";
	$oid_w_['ifInPackets']  = "7";
	$oid_w_['ifOutRate']    = "8";
	$oid_w_['ifInRate']     = "9";
	
	// interface array
	$intdb     = array();
	
	// get interface names and their oid
	$int_info  = snmprealwalk($GLOBALS[argv][2], $GLOBALS[argv][1], ".1.3.6.1.2.1.2.2.1.2");
	$int_mib   = array_keys($int_info);
	$int_val   = array_values($int_info);
	$int_type  = snmpwalk($GLOBALS[argv][2], $GLOBALS[argv][1], ".1.3.6.1.2.1.2.2.1.3");
	
	// and stick the wireless interfaces them in an array
	for ($x=0; $x<count($int_mib); $x++) {
	//	if ($int_type[$x] == "INTEGER: ieee80211(71)" || $int_type[$x] == "INTEGER: other(1)")
			$interfaces[substr($int_val[$x], strpos($int_val[$x], ":") + 2)] = substr($int_mib[$x], strrpos($int_mib[$x], ".") + 1);
	}
	
	// get wireless interface info
	$ssid_info = snmprealwalk($GLOBALS[argv][2], $GLOBALS[argv][1], ".1.3.6.1.4.1.14988.1.1.1.1.1.5");
	$ssid_mib  = array_keys($ssid_info);
	$ssid_val  = array_values($ssid_info);
	
	for ($x=0; $x<count($ssid_mib); $x++) {
		$intdb[substr($ssid_mib[$x], strrpos($ssid_mib[$x], ".") + 1)] = array();
		
		$intdb[substr($ssid_mib[$x], strrpos($ssid_mib[$x], ".") + 1)]["ssid"] = str_replace("\"", "", substr($ssid_val[$x], 
			strpos($ssid_val[$x], ":") + 2));
	}
	
	// get wireless client mac addresses
	$cmac_info = snmprealwalk($GLOBALS[argv][2], $GLOBALS[argv][1], $oid_w_tbl . $oid_w_['ifInSignal']);
	$cmac_mib  = array_keys($cmac_info);
	$cmac_val  = array_values($cmac_info);
	
	for ($x=0; $x<count($cmac_mib); $x++) {
		$counter = count($intdb[substr($cmac_mib[$x], strrpos($cmac_mib[$x], ".") + 1)]["soid"])+1;
		
		$intdb[substr($cmac_mib[$x], strrpos($cmac_mib[$x], ".") + 1)]["soid"][$counter] = substr($cmac_mib[$x], 30, 
			strrpos($cmac_mib[$x], ".") - 30);
		$intdb[substr($cmac_mib[$x], strrpos($cmac_mib[$x], ".") + 1)]['ifIndex'][$counter] = oidtomac(substr($cmac_mib[$x], 30, 
			strrpos($cmac_mib[$x], ".") - 30));
	}
	
	switch ($GLOBALS[argv][3]) {
		case "index": // output interface names
			$int_val = array_keys($interfaces);
			
			for ($x=0; $x<count($int_val); $x++)
				if (count($intdb[$interfaces[$int_val[$x]]]['ifIndex']) > 0)
					for ($y=1; $y<=count($intdb[$interfaces[$int_val[$x]]]['ifIndex']); $y++)
						print $intdb[$interfaces[$int_val[$x]]]['ifIndex'][$y] . "\n";
		break;
		
		case "query":
			switch ($GLOBALS[argv][4]) {
				case "ifIndex":					
					$int_val   = array_keys($interfaces);
					
					for ($x=0; $x<count($int_val); $x++)
						if (count($intdb[$interfaces[$int_val[$x]]]['ifIndex']) > 0)
							for ($y=1; $y<=count($intdb[$interfaces[$int_val[$x]]]['ifIndex']); $y++)
								print $intdb[$interfaces[$int_val[$x]]]['ifIndex'][$y] . "!" . 
									$intdb[$interfaces[$int_val[$x]]][$GLOBALS[argv][4]][$y] . "\n";
				break;
				
				case "ifName":
					$int_val   = array_keys($interfaces);
					
					for ($x=0; $x<count($int_val); $x++)
						if (count($intdb[$interfaces[$int_val[$x]]]['ifIndex']) > 0)
							for ($y=1; $y<=count($intdb[$interfaces[$int_val[$x]]]['ifIndex']); $y++)
								print $intdb[$interfaces[$int_val[$x]]]['ifIndex'][$y] . "!" . $int_val[$x] . "\n";
				break;
			}
		break;
		
		case "get":
			if ($GLOBALS[argv][5] != "") {
				// find the interface, that the BSSID belongs to
				$int_val = array_values($interfaces);
				
				for ($x=0; $x<count($int_val); $x++)
					if (array_search($GLOBALS[argv][5], $intdb[$int_val[$x]]['ifIndex']))
						$int_id = $int_val[$x];
				
				// if debug is requested, print the SNMP OID 
				if ($GLOBALS[argv][6] == "debug")
					print $oid_w_tbl . $oid_w_[$GLOBALS[argv][4]] . mactooid($GLOBALS[argv][5]) . "." . $int_id . "\n";
				
				// get the value reading
				$oid_w_v = snmpget($GLOBALS[argv][2], $GLOBALS[argv][1], $oid_w_tbl . $oid_w_[$GLOBALS[argv][4]] . 
					mactooid($GLOBALS[argv][5]) . "." . $int_id);
				
				if ($oid_w_v != "")
					print substr($oid_w_v, strpos($oid_w_v, ":") + 2);
				else // no value reading returned ?
					print "0";
			}
		break;
		
		default:
			print "Interfaces: ";
			print_r($interfaces);
			print "\n";
			
			print "SSID: ";
			print_r($ssid_mib);
			print "\n";
			print_r($ssid_val);
			print "\n";
			
			print "CMAC: ";
			print_r($cmac_mib);
			print "\n";
			print_r($cmac_val);
			print "\n";
			
			print "Interface database: ";
			print_r($intdb);
	}
?>
