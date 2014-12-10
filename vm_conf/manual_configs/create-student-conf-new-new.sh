#!/bin/bash

if [ $# -ne 3 ]
then
   echo "USAGE: $0 <class number> <start index> <end index>"
   exit;
fi

vm_name_bases=`psql -d vlab -t --command "select vm_name_base from vlab_interim.vnc_base where course='$1'"`

for base_name in ${vm_name_bases[@]}
do


#if [ $# -ne 3 ] 
#then
#   echo "USAGE: $0 <base name> <start index> <end index> "
#   exit;
#fi

#base_name=$1
let sindex=$2
let eindex=$3


template_name=`psql -d vlab -t --command "select conf_template from vlab_interim.vnc_base where vm_name_base='$base_name'"`

##echo "psql -d vlab --command \"" >> $outfile
#for vm_name_base in ${vm_name_bases[@]}
#do

vnc_base=`psql -d vlab -t --command "select vnc_base from vlab_interim.vnc_base where vm_name_base = '${base_name}'"`

echo "base name: " $base_name 
echo "start index: " $2 
echo "end index: " $3
echo "template name: " $template_name 
echo "vnc base port: " $vnc_base
#template="linux_student_template.conf" 
#template_name=$1
#base_name=$2
#let vnc_base=$3
#let sindex=$4
#let eindex=$5

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
	sed $sed_string ${template_name} > ${outfile}

done

done
