#!/bin/bash

echo -- $1 $2 
let sindex=$1
let eindex=$2


#for i in {161..320}
for ((i=sindex; i <= eindex; i++))
do

  let num=$i
  let net=$i+100
  let xen_id=($num%9)+11

  if [ $xen_id -eq 13 ]; then
  	let xen_id=12
  fi

  net_name="Net-${net}"
  xen_server="vlab-bld-${xen_id}"

  echo "moving linux_student to ${xen_server}"
  psql -d vlab --command "UPDATE vlab_interim.vm_resource set xen_server='${xen_server}' WHERE vm_name='linux_student${num}';"

done

