#!/bin/bash


echo "template: " $1 
echo "vnc base port: " $2 
echo "start index: " $3 
echo "end index: " $4
#template="linux_student_template.conf" 
base_name=$1
let vnc_base=$2
let sindex=$3
let eindex=$4

for ((i=sindex; i <= eindex; i++))
#for i in {161..320}
#for i in {1..2}
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
	sed $sed_string ${base_name}_template.conf  > ${outfile}

done

