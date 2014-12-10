#The following script is used in order to create a VM Disk once the bridge     #between network nodes has been setup for storage.
#The script fetches the disk name and resource disk allocated to the VLAN #created.
#!/bin/bash
dname=$1
rdsk=$2

#The disk name is matched alongside the resource disk and in case it exists #then a qcow form of 
#backing image is created at the path /home/OS_images where src for all the #related disk images is maintained.

if [ $dname -a $rdsk ]; then
	if [ ! -f /home/$rdsk.qcow ]; then
		echo "Creating qcow backing image: $rdsk.qcow"
		gunzip -c /home/OS_images/$rdsk.qcow.gz > /home/$rdsk.qcow
		if [ $? -ne 0 ]; then
			echo "Could not create qcow backing image"
			exit 1
		fi
	fi


#once the path for the same is checked and the image for it does not exist we #can create a qcow image #for the asssociated disk resource name. 
	if [ ! -f /home/$dname$rdsk.qcow ]; then
		if [ ! -f /home/vmdsk/$dname$rdsk.qcow.gz ]; then
			echo "Creating qcow COW image: $dname$rdsk.qcow"
			qcow-create 1000 /home/$dname$rdsk.qcow /home/$rdsk.qcow

#In case a dupliacte or incorrect path the creation fails.
			if [ $? -ne 0 ]; then
				echo "Could not create COW image"
				exit 1
			fi
		else

#Once the creation for the qcow image is done we can copy the same in /vmdsk #directory path 
			echo "Copying COW from /home/vmdsk"
			gunzip -c /home/vmdsk/$dname$rdsk.qcow.gz > /home/$dname$rdsk.qcow
			if [ $? -ne 0 ]; then
				echo "Could not copy COW from /home/vmdsk"
				exit 1
			fi
#once the copying of Qcow image is done the initial copy is removed from its #state.
			rm /home/vmdsk/$dname$rdsk.qcow.gz
		fi
	fi
#After the removal of pre image, we check whether the copy state file exists #which in case it does not we create the same at the specific path .

	if [ -f /home/vmdsk/$dname.state.gz ]; then
		echo "Copying State file"
		gunzip -c /home/vmdsk/$dname.state.gz > /home/$dname.state

#Could not copy the file if in case it exists or an input error occurs
		if [ $? -ne 0 ]; then
			echo "Could not copy state file"
			exit 1
		fi
#once the copying of state file is done the initial copy is removed from its #state.
		rm /home/vmdsk/$dname.state.gz
	fi
else
	echo "Input error"
	exit 1
fi


