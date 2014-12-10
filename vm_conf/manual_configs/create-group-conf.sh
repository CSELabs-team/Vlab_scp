#!/bin/bash


echo "start index: " $1 
echo "end index: " $2
#template="linux_student_template.conf" 
let sindex=$1
let eindex=$2

sh create-student-conf-new.sh int-rtr 30000 $sindex $eindex 
sh create-student-conf-new.sh int-lin 28000 $sindex $eindex
sh create-student-conf-new.sh rtr 8000 $sindex $eindex 
sh create-student-conf-new.sh xp 5000 $sindex $eindex 
sh create-student-conf-new.sh bt4_student 3000 $sindex $eindex 
sh create-student-conf-new.sh linux_student 0 $sindex $eindex
        
