#The following script is used in order to delete an existing bridge connection #between network nodes
#The script fetches the network name and the number allocated to the VLAN #created.

#!/bin/bash
netname=$1
vlannum=$2

#It checks whether the corresponding Network Name passed exists or not using #ifconfig at path /device
echo "checking for $netname"
/sbin/ifconfig $netname >/dev/null 2>/dev/null

# In case the associated Bridge/ Network name exists we can apply the function #to break/bring it down.
if [ $? -ne 0 ]; then
	echo "Bringing the bridge down"
	/sbin/ifconfig $netname down #>/dev/null 2>/dev/null

#Else in case the Network Name does not exist it takes no step and cannot bring #it down.
	if [ $? -ne 0 ]; then
		echo "could not bring bridge down"
		exit 1
	fi

#Once the bridge has been deleted/ Node connection has been deleted we need to #remove the same from the network name under consideration

	echo "Deleting if from $netname"

#the command to delete the node from the network using netname and vlan number #and declaring it NULL.
	/sbin/brctl delif $netname bond0.$vlannum #>/dev/null 2>/dev/null

#Delete the interface only in case a connection exists in the Network for the #given VLAN
	if [ $? -ne 0 ]; then
		echo "Could not delete Vlan interface to bridge"
		exit 1
	fi
#Delete the bridge after the interface for the corresponding bridge has been #deleted
	echo "Deleting bridge $netname"
	/sbin/brctl delbr $netname #> /dev/null 2>/dev/null


#Delete the bridge only in case a connection exists in the Network for the #given VLAN
	if [ $? -ne 0 ]; then

		echo "Bridge creation failed"
		exit 1
	fi

fi

#Once the bridge has been deleted we can search for the VLAN Interface for the #given VLAN number
echo "Checking for Vlan interface $vlannum"
/sbin/ifconfig bond0.$vlannum >/dev/null 2>/dev/null

#Add the Vlan Interface only in case a connection exists in the Network for #the given VLAN

if [ $? -ne 0 ]; then
	echo "Creating Vlan interface for $vlannum"
	/sbin/vconfig add bond0 $vlannum #> /dev/null 2>/dev/null
	if [ $? -ne 0 ]; then
		echo "Vlan creation failed"
		exit 1
	fi

#Once we have configured the interface we can power up the interface for the #associated VLAN
	echo "Bringing up the interface"
	/sbin/ifconfig bond0.$vlannum up

#Only in case the associated Interface exists.
	if [ $? -ne 0 ]; then
		echo "could not bring up vlan iface"
		exit 1
	fi
fi
exit 0


