#!/bin/bash
if [ $# -eq 0 ]
then
	echo "Usage: $0 <vlan>"
	exit
fi

vlan=$1

./bin/busybox dumpleases -f /home/vlab_scp/vmnet_conf/leases/nat-$vlan.leases 
