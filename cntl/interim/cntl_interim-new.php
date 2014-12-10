<?php

error_reporting(E_ALL);

require_once ('auth.php');
require_once('log4php/Logger.php');

Logger::configure('appender_file.properties');


include_once("cntl_check_running.php");
include_once("vm_ctrl_addon.php");
include_once("controller_net_startup_addon.php");


require_once('balancer.php');
/*vnc-related functions*/
require_once('locater.php');

/* vm states */
define('NONE_STATE', -1);
define('START_STATE', 10);
define('STOP_STATE', 11);
define('SAVE_STATE', 12);
define('RESTORE_STATE', 13);
define('RESTART_STATE', 14);
define('REIMAGE_STATE', 16);

/* Load balancer (lb) modes */
define('NONE', 0);
define('CLUSTER_GP1', 1);
define('CLUSTER_GP2', 2);


class DECISION_MKR{
/*
*	pick_server is parent() will inherited classes will use 
*	this to pick server
*
*	(top - down)
*	pick_server() -> updateDB() -> updateLocation() 
*		|
*	compareloads()
*		|
*	findAllServerNames()
*		|
*	getResources()
*
*/

	protected $blades;
	protected $blade;

	public $logger;


	private function getTotalBladeMem($xenname){
/*
*
* This function will use the base value
* from the database. The value in the 
* database is not optimal, but is
* suffice for now as we research other 
* ways to get a more actual measurement of
* load
*/

		$query = "SELECT total_memory FROM vlab_interim.servers ".
			"WHERE xen_server='$xenname'";
	
		$result = pg_query($query);
	
		$rows = pg_fetch_assoc($result);

		return $rows["total_memory"]; 

	}

	private function findAllServerNames($lb_mode){
		$rtrn = array();
		$this->blades = array();

		//$this->blades[0] = "vlab-bld-x1";
		$result = pg_query("SELECT xen_server FROM ".
				"vlab_interim.lb_group WHERE ".
				"group_id=(SELECT lb_group FROM vlab_interim.lb_mode WHERE mode=".$lb_mode.")");
				
	//	$result = pg_query("SELECT xen_server FROM vlab_interim.servers");		

		$numOfRows = pg_num_rows($result);

    		for ( $row = 0; $row < $numOfRows; ++$row ){
			array_push($rtrn,pg_fetch_assoc($result, $row));
			
			$tempBlade = new BLADE();
			$tempBlade->setXenName($rtrn["$row"]["xen_server"]);

			$mem = $this->getResource($rtrn["$row"]["xen_server"]);

			/*Each value in DB is NOT in MB this will change it to MB scale*/
			$tempBlade->setUsedMem($mem*pow(10, 6));	
			
			$btmem = $this->getTotalBladeMem($rtrn["$row"]["xen_server"]);
			$tempBlade->setTotalMem($btmem);
	
			$tempBlade->setUsagePercent();

			$this->logger->debug("Server ".
					$tempBlade->getXenName()." has ".
					$tempBlade->getUsedMem().
					"  Bytes of memory used");
			
			$this->logger->debug("Server percent ".
					$tempBlade->getUsagePercent());

			array_push($this->blades, $tempBlade);
		}
		
/*
* These comments are here to know how to access array 
* 
* Exmaple:
* print "".$this->blades[0]->getXen_Name() ."\n";
*
* $mem = $this->getResource($this->blades[0]->getXen_Name());
*
* print "". $this->blades[0]["xen_server"] . "\n" 
* . $this->blades[1]["xen_server"] . 
* $this->blades[2]["xen_server"];
*/

/* return an array of all server names */
	}

        private function getResource($xenname){
/*
* Get the sum of all VMs in START_STATE (10)
* for per blade server. Return the total
* memory of the server. This function will query
* the DB for all VMs and add each VM's memory usage
* This is in the scale of Megabytes (MB)
*
*/

		$total_mem = 0;	

		$query = "SELECT memory FROM vlab_interim.vm_resource ".
			"WHERE xen_server='$xenname' AND current_state='10'";
		
		$result = pg_query($query);
	
		$rows = pg_num_rows($result);

		for($x = 0; $x < $rows; ++$x){
			$tmp = pg_fetch_array($result, $x);
			$total_mem += intval($tmp[0]);
		}
		
		return $total_mem;
		
	}

	private function compareloads($lb_mode){
/*
* This function will call getResources
* for each blade server and compare the two servers
* It will get the total memory in used on each server
* and will compare the server's total memory
* 
* xend-config.sxp - Xen daemon configuration file
* 
* A basic calculation approach will be taken for each blade.
* Total of VMs running / Total of Memory
* 
* Server is chosen by it having the least usage (most available
* resources). If all servers have the same available choose 
* one by
* random.
*
* Next the loader runs again the memory usage will change
* so a new min will be picked. If two+ servers have the 
* same available
* resources the first one will be picked always as it 
* does not matter
* which one was pick as the others will be picked next time.
*/

		$this->findAllServerNames($lb_mode);

		$pickedBladed;
		//ISSUE: $blades is not being initialized
		$this->logger->debug("Blade ".
				$this->blades[0]->getXenName()."has ".
				$this->blades[0]->getUsagePercent());

		$min = $this->blades[0]->getUsagePercent();
		$pickedBladed = array( $this->blades[0]->getXenName() => $min);
		$minpercents;


/* Test if all Servers have the SAME usage percentage, */
/* if so pick a random one */
		$allsame = 0;
		$blademem = array();

		for($count = 0; $count < count($this->blades); ++$count){
			$xenname = $this->blades["$count"]->getXenName();
			$xenmem = $this->blades["$count"]->getUsedMem();

			array_push($blademem, $xenmem);
		}

		$memNums = array_count_values($blademem);

		for($count = 0; $count < count($memNums); ++$count){
			$key = $this->blades["$count"]->getUsedMem();

			if($memNums["$key"] == count($this->blades)){
				$this->logger->debug("ALL SERVERS HAVE THE ".
					"SAME USAGE PERCENTAGE");

				//$rpicked = rand()%count($this->blades);
				$rpicked = 0;
				$percent = $this->blades["$rpicked"]->getUsagePercent();

				$pickedBladed = array( $this->blades["$rpicked"]->getXenName() => $percent);
				$this->logger->debug($this->blades["$rpicked"]->getXenName()." was picked to start next vm on with ".$this->blades["$rpicked"]->getUsagePercent()."% of total memory used");
			}
		}

/* If all server is not the same. Find minimum and save the */
/* location of array to know which blade */
		if($allsame == 0){
			for($count = 0; $count < count($this->blades); ++$count){
				$indivpercen = $this->blades["$count"]->getUsagePercent();
	
				if($indivpercen < $min){
					$min = $indivpercen;
	
					$pickedBladed = array( $this->blades["$count"]->getXenName() => $min);
				}
			}
		}
	
		return key($pickedBladed);		
	}
	
	public function pickServer($action){
/*
* This function finds out which server to start the next
* client. It does this by locating the cluster of VMs
* finding out which one is already started. If one is
* already started move the new client to that server.
* If not move (update DB) to the newly picked server
*
*/
		
/* All other states do not need the use of the load balancer */
/* Since the actions are on the same server */
		if($action["requested_state"] == START_STATE){

			$this->logger->debug($action["vm_name"].
					" will start with mode ".
					$action["lb_mode"]);

			$pickedserver;
			

/*
* Found out where the other clusters are
* if anyone of them have already started, the requested
* VM will be started there. If not assign a new server
* to start on.
*
*/	

			$clusterserver = $this->locateCluster($action["vm_name"]);

/*
* If a cluster server was found and it NOT the same 
* as the pickedserver
* Update server selection
*/

			
			if($clusterserver != "NONE"){
				
/* Replace the picked server with cluster server b/c a VM was found running */
				$pickedserver = $clusterserver; 
				$this->logger->debug("Cluster FOUND (located currently running VM)");
				$this->logger->debug($action["vm_name"]." will be started with others on ".$pickedserver);
			}else{
				$pickedserver = $this->compareloads($action["lb_mode"]);
			}

			
/*
* Now with the selected server, update the DB
* and update the DB with the new host (server)
* We need to find the reflector ID to find the VNC conf
* file. Once found, get the vnc conf file and editted it.
* Change the host and port in the conf file. The host is the 
* new picked server and the port is from the DB.
* After conf editting is done, start vncreflector
* Note during shutdown the vncreflector is killed
* by getting its PID and port number see ReflectorSession.php
*/	

			$this->updateDB($action, $pickedserver);

			return $pickedserver;
		}/*if START_STATE*/
	}

	private function updateDB($action, $pickedserver){
/*
* After a server if picked, updated it in the DB
*/
	
		$query = "UPDATE vlab_interim.vm_resource SET xen_server='".
			$pickedserver."' WHERE vm_name='".
			$action["vm_name"]."'";
		

		$result = pg_query($query);

	}
	
	private function updateLocation(){
/*
* Send signal to VNCreflector and update conf file
* NOTE: do not need to as the initial creation of these
* files happen after the main script checks that the VM's
* current state is START_STATE (aka. after remote script 
* is executed)
*/
	}

	private function massMigrate(){
/*
* If a client requested to start on a server and
* it was discovered that the requested server 
* resources are max'd
*
* Move all of clients VMs to the assigned server
*/

	}
	
	public function locateCluster($vm_name){
/*
* Find out:
* 1) who owns the VM
* 2) other vms own by that user
* 
* If other VMs are currently running start new VM there.
* If not, keep the previous assigned server
*
*/	

		$nodes = array();

/*find out the (client) student ID for this VM*/
		$locuserquery = "select user_name from vlab_interim.user ".
				"where user_id in (select user_id from ".
				"vlab_interim.user_reflector where ".
				"reflector_id in (select reflector_id ".
				"from vlab_interim.reflector where vm_id ".
				"= (select vm_id from ".
				"vlab_interim.vm_resource where vm_name ".
				"= '$vm_name')))";

		$result = pg_query($locuserquery);
	
		$student = pg_fetch_assoc($result);/*user_name*/
		
		$tmp = $student["user_name"];

/* find out all of the client's other VMs, their status, and */
/* the server they are on*/

		$locuservmquery = "select vm_id, vm_name, requested_state, ".
		"current_state, xen_server from vlab_interim.vm_resource ".
		"where vm_id in (select vm_id from vlab_interim.reflector ".
		"where reflector_id in (select reflector_id ".
		"from vlab_interim.user_reflector where user_id ".
		"= (select user_id from vlab_interim.user where ".
		"user_name = '$tmp')))";
	
		$result = pg_query($locuservmquery);

		$rows = pg_num_rows($result);

		for($row = 0; $row < $rows; ++$row){
			$node = pg_fetch_assoc($result, $row);
			
/*vm_id, vm_name, requested_state, current_state, xen_server*/

			$this->logger->debug($node["vm_name"]." is on ".$node["xen_server"]);

/*if a VM in clust has already started return that xen_server name*/

			if($node["current_state"] == START_STATE){
				return $node["xen_server"];			
			}

			array_push($nodes, $node);
		}

		
/*At this point no VMs in cluster are already started*/
		return "NONE";		
	}
}


/*
* This class is for obtaining all VMs from DB
* using the load balancing inherited functions
* and to act on the requested actions 
*/
class VM_LIST extends DECISION_MKR{
  
	public function VM_LIST( ) {

	$this->logger =& Logger::getLogger(get_class($this));

  }
 
  private function dbConnect( ){

    #$this->db_conn = pg_pconnect("host=127.0.0.1 dbname=vlab user=postgres password=postgres");
    $this->db_conn = pg_pconnect("host=localhost dbname=vlab user=postgres password=postgres");
    
    if (!$this->db_conn)
      throw new Exception('Error connecting to db');
    

  }


  public function processActions( ){

	$this->clearDisabled();
	$neededaction = $this->getNeededAction();

	foreach ($neededaction as $action) {

/*
* 1) Check mode 
*  1a) if mode none skip steps 2-3, go to 4
*  1b) if not continue with steps
* 2) Get blade to start VM on 
* 3) Check if there are already been VMs started in the cluster
*  assigned it there
* 3a) IF NO other VMs in cluster are started do massMigrate()
*  ("update DB") to the new server and start the VM there
* 4) start requested VM there
* 5) repeat. next request
*/

		if($action["lb_mode"] == NONE){
			/*start with no load balancing enable*/
			$this->logger->debug($action["vm_name"]." will work".
				" on LB_MODE = NONE");
			
		}else{

			if($action["requested_state"] == START_STATE){

/* Update xen_server to new server */
				$this->blade = $this->pickServer($action);
				$action["xen_server"] = $this->blade; 
			}
		}

		switch( $action["requested_state"] ) {
			case START_STATE:
				$this->doAction($action, "startup");
				break;
			case RESTART_STATE:
				$this->doAction($action, "restart");
				break;
			case STOP_STATE:
				$this->doAction($action, "shutdown");
				break;
			case SAVE_STATE:
				if( $action["current_state"] != START_STATE ) {
					$this->doAction($action, "save");
				}
				else {
    	  	$this->logger->error("vm not in running to save state: "
						.$action["requested_state"]
						." ".$action["xen_server"]
						." ".$action["vm_name"]."\n");
				}
			case RESTORE_STATE:
				if( $action["requested_state"] != SAVE_STATE ) {
					$this->doAction($action, "shutdown");
				}

				# if the current state is running, stop 
				if( $action["current_state"] != START_STATE ) {
					$this->doAction($action, "shutdown");
				}

				$this->doAction($action, "restore");
				
				# if the current state was running, start it back up 
				if( $action["current_state"] != START_STATE ) {
					# now set state to running
					$action["requested_state"]=START_STATE;
					$this->setState($action);
				}
				else {
					# now set state to running
					$action["requested_state"]=STOP_STATE;
					$this->setState($action);
				}
				break;
			case REIMAGE_STATE:

				$this->doAction($action, "reimage");
				
				break;
			default:
				$this->logger->error($action["requested_state"]
					." ".$action["xen_server"]
					." ".$action["vm_name"]."\n");
		}

/*
* Needed to correct an issue when two VMs owned by the same client
* are started at the same time, the script will not see that the
* first VM was not started.
* Fifteen (15) is the minimum time that can be set so this situation does
* not occur
*/

		if($action["requested_state"] == START_STATE){		
			//sleep(15);
			//temporary removed check if above issues still 
			//exists
		}

	}
 

  }



  private function clearDisabled( ){

    $this->dbConnect();

    $query = sprintf('UPDATE vlab_interim.vm_resource 
		SET inprocess=%d, 
		requested_state=%d, 
		current_state=%d
      		WHERE disabled=1', 0, STOP_STATE, STOP_STATE);
      
    $result = pg_query($this->db_conn, $query );
    
    if (!$result) {
	      throw new Exception('Error executing query');
    }

  }

  private function getNeededAction( ){


    $this->dbConnect();

    $query = sprintf( 'SELECT xen_server, vm_name, current_state, requested_state, vlan_id, lb_mode
      FROM vlab_interim.vm_resource
      WHERE requested_state!=current_state AND inprocess=0' );

    $result = pg_query($this->db_conn, $query );
    
    if (!$result)
      throw new Exception('Error executing query');
    
    $rtrn = array();
    $rows  = pg_num_rows($result);
    for ( $row = 0; $row < $rows; ++$row ){
      array_push($rtrn,pg_fetch_assoc($result,$row));
    }

  

    foreach ($rtrn as $action) {

    	$query = sprintf( 'UPDATE vlab_interim.vm_resource 
			SET inprocess=1
      			WHERE vm_name=\'%s\'',$action["vm_name"] );
      
        $result = pg_query($this->db_conn, $query );
    
    	if (!$result)
	      throw new Exception('Error executing query');
    
    }

	

    return $rtrn;
  }

  private function checkStarted($vm_name ){

    $this->dbConnect();

    $query = sprintf( 'SELECT current_state FROM vlab_interim.vm_resource
      WHERE vm_name=\'%s\'',
		pg_escape_string( $this->db_conn, $vm_name) );
      
    $result = pg_query($this->db_conn, $query );
    
    if (!$result)
      throw new Exception('Error executing query');
    
    $rtrn = array();
    $rows  = pg_num_rows($result);
    for ( $row = 0; $row < $rows; ++$row ){
      array_push($rtrn,pg_fetch_assoc($result,$row));
    }
    
    return $rtrn;
  }

  private function getReimageInfo($vm_name ){

    $this->dbConnect();

    $query = sprintf( 'SELECT reimage_file, disk_image FROM vlab_interim.vm_resource WHERE vm_name=\'%s\'', pg_escape_string($this->db_conn, $vm_name) );
      
    $result = pg_query($this->db_conn, $query );
    
    if (!$result)
      throw new Exception('Error executing query');
    
    $rtrn = array();
    $rows  = pg_num_rows($result);
    if ($rows != 1)
      throw new Exception('Error getting reimage infomation for '.$vm_name);
    
    $rtrn = pg_fetch_assoc($result);
    
    return $rtrn;
  }

  private function doAction ($action, $task) {
	
	$this->logger->debug($task.": "
		.$action["xen_server"]." "
		.$action["vm_name"]);

	       $xen_server = $action["xen_server"];
	       $vm_name = $action["vm_name"];
	       $vlan_id = $action["vlan_id"];
	
/*Old comment see original scrip*/
	if ($task == "reimage") {

		$reimage_info = $this->getReimageInfo($vm_name);
		$disk_image = $reimage_info["disk_image"];
		$reimage_file = $reimage_info["reimage_file"];
/*
* reimage.sh <xen server> <vm name> <vlan id> <disk_image> <reimage_file>
*/

		$exec_string = "./reimage.sh $xen_server $vm_name $vlan_id $disk_image $reimage_file >> doAction_output.log  2>&1 &";

		$this->logger->debug($exec_string);
               exec($exec_string,$msgs,$retval);

		if($retval!=0){
			$this->logger->error("unable to exec vm command: ".$exec_string);
		}

	}
	else {
        if ($task == "startup") { CreateBridges($xen_server, $vm_name); }
               $exec_string = "./remote_exec.sh $xen_server $task ". $action["requested_state"] . " $vm_name $vlan_id >> doAction_output.log  2>&1 &";
    	       $this->logger->debug($exec_string);
		exec($exec_string,$msgs,$retval);
	
		if($retval!=0){
			$this->logger->error("unable to exec vm command: ".$exec_string);
		}

	}

  }
  
  private function setState( $action ) {

    $query = sprintf( 'UPDATE vlab_interim.vm_resource SET timestamp=now(), current_state=%s, requested_state=%s, inprocess=0 WHERE xen_server=\'%s\' AND vm_name=\'%s\'',
	      pg_escape_string( $this->db_conn, $action["requested_state"]),
	      pg_escape_string( $this->db_conn, $action["requested_state"]),
	      pg_escape_string( $this->db_conn, $action["xen_server"]),
	      pg_escape_string( $this->db_conn, $action["vm_name"]));
    
    $this->logger->debug($query);
    $result = pg_query($this->db_conn, $query );
    if (!$result)
 	throw new Exception('Error executing query'.$query);
  }
  
  
  private $vm_name;
  private $xen_server_name;
  private $db_conn;
}


###  Main loop

function main() {

$logger =& Logger::getLogger('main');

	$vm_list = new VM_LIST();

	while(1) { 

  		$logger->debug('looping');
		try {
		  
            $vm_list->processActions(); 
            /* these are the two new commands: Aug, 2011 */

            ProcessDB();
            ProcessDel();
            ProcessRes();
	
		} 
		/* something bad */
		catch (Exception $e) {
 			$logger->error( $e );
		}
		#JL sleep(10);
  		sleep(10); 
	}
}

$logger =& Logger::getLogger('main');


try {

  $logger->debug('starting main()');
  main();

} catch ( Exception $e ){
  $logger->error( $e );
}

?>
