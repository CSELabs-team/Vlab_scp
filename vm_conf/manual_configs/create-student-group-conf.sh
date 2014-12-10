#!/bin/bash

if [ $# -ne 3 ]
then
   echo "USAGE: $0 <class number> <start index> <end index>"
   exit;
fi

vm_name_bases=`psql -d vlab -t --command "select vm_name_base from vlab_interim.vnc_base where course='$1'"`

for base_name in ${vm_name_bases[@]}
do

	course=$1
	let sindex=$2
	let eindex=$3

	./create-single-config.sh $course $base_name $sindex $eindex

done
