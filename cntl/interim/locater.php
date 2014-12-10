<?php

/*This file is used to manipulate the client's vncreflector config file*/


function getVNCPort($reflector_id){

	$result =  pg_query("SELECT vnc_port FROM vlab_interim.vm_resource, vlab_interim.reflector ".
      "WHERE vlab_interim.reflector.vm_id=vlab_interim.vm_resource.vm_id ".
        "AND vlab_interim.reflector.reflector_id='$reflector_id'");

	$row = pg_fetch_assoc($result); /*vnc_port*/
    
    return $row['vnc_port'];
  }

function getReflectorId($vm_name){

	$result = pg_query("SELECT reflector_id FROM vlab_interim.reflector WHERE vm_id=(SELECT vm_id FROM vlab_interim.vm_resource WHERE vm_name='$vm_name')");
	
	$row = pg_fetch_assoc($result); 

	return $row["reflector_id"];

}

?>
