#!/bin/bash

if [ $# -ne 5 ] 
then
   echo "USAGE: $0 <base name> <start index> <end index> <string to find> <string to replace>"
   exit;
fi

base_name=$1
let sindex=$2
let eindex=$3
fstring=$4
rstring=$5

backup_dir=`date +%s`
mkdir replace_backup/${backup_dir}/

echo "base name: " $base_name 
echo "start index: " $2 
echo "end index: " $3
echo "replacing --" $fstring "-- with --" $rstring " --"

for ((i=sindex; i <= eindex; i++))
do
 	cp ${base_name}${i}.conf replace_backup/${backup_dir}/
 	sed  -i "s/${fstring}/${rstring}/" ${base_name}${i}.conf

done

