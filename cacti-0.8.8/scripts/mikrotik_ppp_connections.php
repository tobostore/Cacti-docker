<?php
	$no_http_headers = true;
	error_reporting(0);
	snmp_set_oid_numeric_print(TRUE);
	
	// if oid table
	$oid_if_tbl = ".1.3.6.1.2.1.2.2.1.";
	$oid_if_['ifIndex']		= "1";
	$oid_if_['ifDescr']		= "2";
	$oid_if_['ifType']		= "3";
	$oid_if_['ifMtu']		= "4";
	$oid_if_['ifSpeed']		= "5";
	$oid_if_['ifPhysAddress']	= "6";
	$oid_if_['ifAdminStatus']	= "7";
	$oid_if_['ifOperStatus']	= "8";
	$oid_if_['ifLastChange']	= "9";
	$oid_if_['ifInOctets']		= "10";
	$oid_if_['ifInUcastPkts']	= "11";
	$oid_if_['ifInNUcastPkts']	= "12";
	$oid_if_['ifInDiscards']	= "13";
	$oid_if_['ifInErrors']		= "14";
	$oid_if_['ifInUnknownProtos']	= "15";
	$oid_if_['ifOutOctets']		= "16";
	$oid_if_['ifOutUcastPkts']	= "17";
	$oid_if_['ifOutNUcastPkts']	= "18";
	$oid_if_['ifOutDiscards']	= "19";
	$oid_if_['ifOutErrors']		= "20";
	$oid_if_['ifOutQLen']		= "21";
	
	// if more oid table
	$oid_im_tbl = ".1.3.6.1.2.1.30.1.1.1.";
	$oid_im_['ifName']		= "1";
	$oid_im_['ifInMulticastPkts']	= "2";
	$oid_im_['ifInBroadcastPkts']	= "3";
	$oid_im_['ifOutMulticastPkts']	= "4";
	$oid_im_['ifOutBroadcastPkts']	= "5";
	$oid_im_['ifHCInOctets']	= "6";
	$oid_im_['ifHCInUcastPkts']	= "7";
	$oid_im_['ifHCInMulticastPkts']	= "8";
	$oid_im_['ifHCInBroadcastPkts']	= "9";
	$oid_im_['ifHCOutOctets']	= "10";
	$oid_im_['ifHCOutUcastPkts']	= "11";
	$oid_im_['ifHCOutMulticastPkts']= "12";
	$oid_im_['ifHCOutBroadcastPkts']= "13";
	$oid_im_['ifHighSpeed']		= "15";
	
	// ppp oid table
	$oid_ppp_tbl = ".1.3.6.1.4.1.9.9.150.1.1.3.1.";
	$oid_ppp_['pppUser']		= "2";
	$oid_ppp_['pppIp']		= "3";
	
	// queues oid table
	$oid_qs_tbl = ".1.3.6.1.4.1.14988.1.1.2.1.1.";
	$oid_qs_['qsDescr']		= "2";
	$oid_qs_['qsTargIp']		= "3";
	$oid_qs_['qsTargMask']		= "4";
	$oid_qs_['qsDstIp']		= "5";
	$oid_qs_['qsDstMask']		= "6";
	$oid_qs_['qsIf']		= "7";
	$oid_qs_['qsInOctets']		= "8";
	$oid_qs_['qsOutOctets']		= "9";
	$oid_qs_['qsInPackets']		= "10";
	$oid_qs_['qsOutPackets']	= "11";
	
	// get interface names and their oid
	$int_info = snmprealwalk($GLOBALS[argv][2], $GLOBALS[argv][1], $oid_if_tbl . $oid_if_['ifDescr']);
	$int_mib  = array_keys($int_info);
	$int_val  = array_values($int_info);
	$int_type = snmpwalk($GLOBALS[argv][2], $GLOBALS[argv][1], $oid_if_tbl . $oid_if_['ifType']);
	
	for ($x=0; $x<count($int_mib); $x++) {
		// check if it is ppp connection
		if ($int_type[$x] == "INTEGER: ppp(23)") {
			$tmp_if = substr($int_val[$x], strpos($int_val[$x], ":") + 2);
			
			// check if it is dynamic
			if ($tmp_if{0} == "<" && substr($tmp_if, -1) == ">") {
				$tmp_name = substr($tmp_if, strpos($tmp_if, "-") + 1, -1);
				
				$interfaces[$tmp_name]['Index']		= $tmp_name;
				$interfaces[$tmp_name]['Name']	 	= $tmp_name;
				
				$interfaces[$tmp_name]['ifIndex']	= substr($int_mib[$x], strrpos($int_mib[$x], ".") + 1);
				$interfaces[$tmp_name]['ifDescr']	= $tmp_if;
				$interfaces[$tmp_name]['ifType']	= "ppp(23)";
				$interfaces[$tmp_name]['ifPhysAddress']	= "";
				$interfaces[$tmp_name]['ifAdminStatus']	= "up(1)";
				$interfaces[$tmp_name]['ifOperStatus']	= "up(1)";
				$interfaces[$tmp_name]['ifLastChange']	= "(0) 0:00:00.00";
				
				$interfaces[$tmp_name]['ifName']	= $tmp_if;
				$interfaces[$tmp_name]['ifHighSpeed']	= "0";
				
				$interfaces[$tmp_name]['pppUser']	= $tmp_name;
				
				$interfaces[$tmp_name]['qsDescr']	= $tmp_if;
			}
		}
	}
	
	switch ($GLOBALS[argv][3]) {
		case "index":
			foreach ($interfaces as $interface)
				echo $interface['Name'] . "\n";
		break;
		
		case "query":
			if ($GLOBALS[argv][4] != "")
				foreach ($interfaces as $interface)
					echo $interface['Name'] . "!" . $interface[$GLOBALS[argv][4]] . "\n";
		break;
		
		case "get":
			if (is_array($GLOBALS[argv][4])) {
				switch ($GLOBALS[argv][5]) {
					case	"ifIndex" || "ifDescr" || "ifType" || "ifMtu" || "ifSpeed" ||
						"ifPhysAddress" || "ifAdminStatus" || "ifOperStatus" || "ifLastChange" ||
						"ifInOctets" || "ifInUcastPkts" || "ifInNUcastPkts" || "ifInDiscards" ||
						"ifInErrors" || "ifInUnknownProtos" || "ifOutOctets" || "ifOutUcastPkts" ||
						"ifOutNUcastPkts" || "ifOutDiscards" || "ifOutErrors" || "ifOutQLen"
					:
						$oid_ = $oid_if_tbl . $oid_if_[$GLOBALS[argv][5]] . "." . $interfaces[$GLOBALS[argv][4]]['ifIndex'];
					break;
					
					case	"ifName" || "ifInMulticastPkts" || "ifInBroadcastPkts" ||
						"ifOutMulticastPkts" || "ifOutBroadcastPkts" || "ifHCInOctets" ||
						"ifHCInUcastPkts" || "ifHCInMulticastPkts" || "ifHCInBroadcastPkts" ||
						"ifHCOutOctets" || "ifHCOutUcastPkts" || "ifHCOutMulticastPkts" ||
						"ifHCOutBroadcastPkts" || "ifHighSpeed"
					:
						$oid_ = $oid_im_tbl . $oid_if_[$GLOBALS[argv][5]] . "." . $interfaces[$GLOBALS[argv][4]]['ifIndex'];
					break;
					
					case	"pppUser" || "pppIp"
					:
						$oid_ = $oid_ppp_tbl . $oid_if_[$GLOBALS[argv][5]] . ".";
					break;
					
					case	"qsDescr" || "qsTargIp" || "qsTargMask" || "qsDstIp" || "qsDstMask" ||
						"qsIf" || "qsInOctets" || "qsOutOctets" || "qsInPackets" || "qsOutPackets"
					:
						$oid_ = $oid_qs_tbl . $oid_if_[$GLOBALS[argv][5]] . ".";
					break;
					
					default:
						$oid_ = "";
				}
				
				echo $GLOBALS[argv][4] . "!";
				
				if ($oid_ != "")
					echo snmpget($GLOBALS[argv][2], $GLOBALS[argv][1], $oid_);
				else
					echo "0";
				
			} else if ($GLOBALS[argv][4] != "")
				echo $GLOBALS[argv][4] . "!" . "0";
		break;
		
		default:
			print "Interfaces: ";
			print_r($interfaces);
			print "\n";
	}
?>
