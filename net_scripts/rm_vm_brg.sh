#The following script is used in order to remove a VM bridge.
#The script declares a variable for maintaining a count for the same.

#!/bin/bash
COUNT=0
#It loops for a list of all the networks  in the path /sys/class/net for the #inputted interface.

for i in `ls /sys/class/net`
do
	for j in `ls /sys/class/net/$i`
	do
#Checks in the listing of all the networks from the path and in case any bridge #type is encountered

		if [ $j = "bridge" ]
		then
			for k in `ls /sys/class/net/$i/brif`

			do
#Increments the counter for such bridges by 1
				(( COUNT += 1 ))
				IFACE[$COUNT]=$k
#Allocates the value at IFACE array at the count value to another variable .

			Done
#The bridge which has a counter less than 1 we need to delete the same.
			if [ $COUNT -le 1 ]
			then
#The following set of commands eases in bridge deletion, fetches IP for the #same and deletes the bridge control for the same and at the same time updates #the vmconfig f#or removing the same. It finally brings down the bridge for the #same in reference to the associated IP Address.
				echo "Deleting bridge $i"
				ifconfig ${IFACE[$COUNT]} down
				brctl delif $i ${IFACE[$COUNT]}
				vconfig rem ${IFACE[$COUNT]}
				ifconfig $i down
				brctl delbr $i
			fi
		fi
	done
done




