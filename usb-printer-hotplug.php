#!/usr/bin/php
<?PHP

$vmName       = "Print Server";     # must be the exact name
$pollingTime  = 30;                 # the interval between checks in seconds
$startupDelay = 90;                 # startup delay before monitoring for changes in seconds (set to enough time for the VM to get up and running)
$deviceID     = "abcd:1234";        # device ID
$xml_filename = "hp_printer.xml";   # XML filename in /tmp


function isDeviceConnected( $deviceID ) {
  exec("lsusb | grep 'ID $deviceID'",$output);
  return !empty($output);
}

function createXML($deviceID, $xml_filename) {
  $usb = explode(":",$deviceID);
  $usbstr .= "<hostdev mode='subsystem' type='usb'>
                <source>
                  <vendor id='0x".$usb[0]."'/>
                  <product id='0x".$usb[1]."'/>
                </source>
              </hostdev>";
  file_put_contents("/tmp/".$xml_filename,$usbstr);
}

function logger($string) {
  //echo "logger ".escapeshellarg($string);
  shell_exec("logger ".escapeshellarg($string));
}
        
# Begin Main
logger("Sleeping for $startupDelay before monitoring $bus for changes to passthrough to $vmName");
sleep($startupDelay);

logger("Monitoring $deviceID for changes");
$device_state = isDeviceConnected($deviceID);


while (true) {
  $unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
  if ($unRaidVars['mdState'] != "STARTED") {
    break;
  }

  $current_state = isDeviceConnected($deviceID);
  if ($device_state != $current_state) {
    createXML($deviceID, $xml_filename);

    if ($current_state) {
      // device connected
      logger("$deviceID has been connected. Attaching to $vmName");
      exec("/usr/sbin/virsh attach-device '$vmName' /tmp/$xml_filename 2>&1");
    }
    else {
      // device disconnected 
      logger("$deviceID has been diconnected. Detaching from $vmName");
      exec("/usr/sbin/virsh detach-device '$vmName' /tmp/$xml_filename 2>&1");
    }

    $device_state = $current_state;
  }  

  sleep($pollingTime);
}

?>
