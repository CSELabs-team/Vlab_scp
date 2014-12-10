<?php

class BLADE{
	private $usedmemory;
	private $totalmem;
	private $xen_server;
	private $usagepercent;

	public function getUsedMem(){
		return $this->usedmemory;
	}

	public function getXenName(){
		return $this->xen_server;	
	}

	public function getTotalMem(){
		return $this->totalmem;	
	}
	
	public function getUsagePercent(){
		return $this->usagepercent;	
	}

	public function setUsedMem($xmem){
		$this->usedmemory = $xmem;
	}

	public function setXenName($xname){
		$this->xen_server = $xname;	
	}

	public function setTotalMem($tmem){
		$this->totalmem = $tmem;	
	}

	public function setUsagePercent(){
		if($this->totalmem != 0){
			$this->usagepercent = ($this->usedmemory/$this->totalmem)*100;	
		}else{
			$this->usagepercent = 0;
		}
	}
	
}



?>

