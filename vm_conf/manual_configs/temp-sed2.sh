#!/bin/bash

if [ $# -ne 3 ] 
then
   echo "USAGE: $0 <base name> <start index> <end index> "
   exit;
fi

base_name=$1
let sindex=$2
let eindex=$3


echo "base name: " $base_name 
echo "start index: " $2 
echo "end index: " $3

for ((i=sindex; i <= eindex; i++))
do
	sed  "/sdl/i stdvga=1 \nvideoram=16" ${base_name}${i}.conf > temp-out.conf ; mv temp-out.conf ${base_name}${i}.conf

done

