#!/bin/bash

# start-vms.sh <basename> <start index> <end index>

echo -- $1 $2

let sindex=$1
let eindex=$2

for ((i=sindex; i <= eindex; i++))
do

   echo "Starting Destroy ${i}"
   xl destroy $i	

done
