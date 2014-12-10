#The following script is used in order to create a bridge connection    #between network nodes
#The script fetches the network name and the number allocated to the VLAN #created.
#!/bin/bash
netname=$1
vlannum=$2
#It checks whether the corresponding Network Name passed exists or not using #ifconfig at path /device
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

#After the STP has been stepped up we have to bring the bridge up for the #associated Network Name and this fails if the Network name does not exist.
	echo "Bringing the bridge up"
	/sbin/ifconfig $netname up #>/dev/null 2>/dev/null
	if [ $? -ne 0 ]; then
		echo "could not bring bridge up"
		exit 1
	fi
fi

#Once the STP and bridge has been brought up then we will check for the Vlan #Interface for the specific bond0 for the provided vlan number.

echo "Checking for Vlan interface $vlannum"
/sbin/ifconfig bond0.$vlannum >/dev/null 2>/dev/null
if [ $? -ne 0 ]; then
	echo "Creating Vlan interface for $vlannum"

#The vlan interface for the same is added to bond0 for the given Vlan Number in #case the same exists.
	/sbin/vconfig add bond0 $vlannum #> /dev/null 2>/dev/null
	if [ $? -ne 0 ]; then
		echo "Vlan creation failed"
		exit 1
	fi

#Once the interface has been added to bond0 then we have to add the same to the #network using addif for the specific Vlan number else no interface added in #case it does# not exist
	echo "Adding it to $netname"
	/sbin/brctl addif $netname bond0.$vlannum #>/dev/null 2>/dev/null
	if [ $? -ne 0 ]; then
		echo "Could not add Vlan interface to bridge"
		exit 1
	fi

#The final step is to get the interface up using up for bond0 for the Vlan #number under consideration.
#else a failure is reported. 
	echo "Bringing up the interface"
	/sbin/ifconfig bond0.$vlannum up
	if [ $? -ne 0 ]; then
		echo "could not bring up vlan iface"
		exit 1
	fi
fi
exit 0


