<?php

include_once "vm_ctrl_addon.php";

function GetBrdgNameFromVmName($vmname) { 
    $t = "vlab_interim";
    $q = "select brdg_name from $t.vbridge where brdg_id in (select vif_brdg_id from $t.vif where vm_id in (select vm_id from $t.vm_resource where vm_name ='$vmname'));"; 
    l("query = $q");
    $rows = GetRows($q); 

    $sz = count($rows);
    if ($sz==0) { 
        return null;
    } else { 
        $sub = array();
        for ($i=0; $i<$sz; $i++) {
           $sub[$i] = implode(" ", $rows[$i]); 
           $x = $sub[$i];
           l("sub[$i] = $x");
        } 
        print_r($sub);
        $ret = implode(" ", $sub);
        l("making: $ret");
        return($ret); 
    }
}

function CreateBridges($xenserver, $vmname) {
    if ($res = GetBrdgNameFromVmName($vmname)) {
        $cmd = "/home/vlab_scp/net_scripts/mk_vm_nets.sh $xenserver $res";
        l("executing $cmd");
        system($cmd);
    } else {
        l("No Bridges For This Vm_Name");
    }
}


?>
