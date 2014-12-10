#!/bin/bash

if [ $# -ne 5 ]
then
	echo "USAGE: $0 <xen server> <vm name> <vlan id> <disk_image> <reimage_file>"
	exit 1;
fi

xen_server=$1
vm_name=$2
vlan_id=$3
disk_image=$4
reimage_file=$5
task_id=11

date -u;
echo "Shutting down $vm_name for reimage"
#JL increases the ConnectTimeout (5) && sleep (2)
ssh -o ConnectTimeout=5 root@$xen_server "/home/vlab_scp/vm_conf/manual_configs/interim/vlab_ctl.shutdown $vm_name $vlan_id"; 

sleep 2;
#JL
psql -d vlab -t --command "UPDATE vlab_interim.vm_resource SET timestamp=now(), current_state=${task_id}, requested_state=${task_id}, inprocess=0 WHERE xen_server='${xen_server}' AND vm_name='${vm_name}'";

echo "Reimaging $vm_name"
if [ -f /home/vmdsk/$reimage_file ] 
then
  if [ -f /home/vmdsk/$disk_image ]
  then
    #JL rewrite for temporary
    ssh -o ConnectTimeout=10 root@$xen_server "cp /home/vmdsk/$reimage_file /home/vmdsk/$disk_image"; 
    #JL
    echo "$vm_name has been reimaged."
  else
    echo "($disk_image) does not exist";
  fi
else
  echo "($reimage_file) does not exist";
fi
