#!/bin/bash

if [ $# -ne 5 ]
then
	echo "USAGE: $0 <xen server> <task> <task id> <vm name> <vlan id>"
	exit 1;
fi

xen_server=$1
task=$2
task_id=$3
vm_name=$4
vlan_id=$5

date -u;
echo "starting $task on $xen_server";
echo "-- $* --";

#JL increases ConnecTimout(5) && sleep time(2)
ssh -o ConnectTimeout=5 root@$xen_server "/home/vlab_scp/vm_conf/manual_configs/interim/vlab_ctl.$task $vm_name $vlan_id"; 
sleep 2;
#JL

echo "updating db after $task";
psql -d vlab -t --command "UPDATE vlab_interim.vm_resource SET timestamp=now(), current_state=${task_id}, requested_state=${task_id}, inprocess=0 WHERE xen_server='${xen_server}' AND vm_name='${vm_name}'";
