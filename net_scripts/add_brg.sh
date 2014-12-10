#!/bin/bash
netname=$1

#check whether the corresponding Network Name passed exists or not using 
#ifconfig at path /device
echo "checking for $netname"
/sbin/ifconfig $netname >/dev/null 2>/dev/null

#In case the same exists it adds the bridge at the corresponding Network Name
if [ $? -ne 0 ]; then
	echo "Adding bridge $netname"

#For the associated bridge for the corresponding netname we add a connection or #else a failure if the netname does not exist.
	/sbin/brctl addbr $netname #> /dev/null 2>/dev/null
	if [ $? -ne 0 ]; then
		echo "Bridge creation failed"
		exit 1
	fi
#Once the bridge has been added then STP for the same has to be stepped up.
	echo "Set STP off"
	/sbin/brctl stp $netname off #> /dev/null 2>/dev/null

#In case the STP does not exist, STP is not stepped up.
	if [ $? -ne 0 ]; then
		echo "Setting stp off failed"
		exit 1
	fi

#After the STP has been stepped up we have to bring the bridge up for the 
#associated Network Name and this fails if the Network name does not exist.
	echo "Bringing the bridge up"
	/sbin/ifconfig $netname up #>/dev/null 2>/dev/null
	if [ $? -ne 0 ]; then
		echo "could not bring bridge up"
		exit 1
	fi
fi

exit 0


