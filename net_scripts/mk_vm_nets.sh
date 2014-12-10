#!/bin/bash

if [ $# -lt 2 ]
then
   echo "USAGE: $0  <xen server> <bridge> [<bridge name>]"
   exit;
fi

echo -- $1 $2
xenserver=$1
let count=$#

echo "number of arguments is $count" >> /tmp/log

echo "Creating Network $1" >> /tmp/log
args=("$@")
let max=$((count));
for ((i=1; i < $max; i++)) {
	echo "network $((i)): ${args[$i]}" >> /tmp//log
#	echo $xenserver brctl addbr ${args[$i]} 
#	ssh $xenserver brctl addbr ${args[$i]} 
#	echo $xenserver ifconfig ${args[$i]} up
#	ssh $xenserver ifconfig ${args[$i]} up
	
	#create network
	ssh  -y $xenserver /home/vlab_scp/net_scripts/add_brg.sh ${args[$i]}
}
