#!/bin/bash

if [ $# -ne 5 ]
then
	echo "USAGE: $0 <template file> <base name> <vnc base> <start index> <end index>"
	exit 1;
fi

echo "template: " $1 
echo "base name: " $2 
echo "vnc base port: " $3 
echo "start index: " $4 
echo "end index: " $5
template_name=$1
base_name=$2
let vnc_base=$3
let sindex=$4
let eindex=$5

for ((i=sindex; i <= eindex; i++))
do
	foo=256
	bar=100
	vnc=$(($vnc_base + $i))
	first=$(($vnc / $foo))
	second=$(($vnc % $foo))
	mac="$(printf "%02X:%02X" $first $second)"	
	netid=$(($i + $bar))
	outfile="${base_name}${i}.conf"
	echo "Creating $outfile configuration file"
	sed_string='s/STUDENT_VNC/'"${vnc}"'/g;s/STUDENT_MAC/'"${mac}"'/g;s/STUDENT_NUM/'"${i}"'/g;s/STUDENT_NET/'"${netid}"'/g' 
	sed $sed_string ${template_name}  > ${outfile}

done

