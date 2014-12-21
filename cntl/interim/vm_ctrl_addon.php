<?php

include_once "conf.php";

/* API ---------------------------------------------------------------------- */
$log = fopen("log.txt", "a");

$t = "vlab_interim";
$DEBUG = 0;
function dp($line){
	global $DEBUG;
	if ($DEBUG) {
		echo "$line\n";
	}
}

function l($string) {
  global $log;
  //dp("writing $string to log");
  $now = date("g:i:s a");
	fwrite($log, "$now : $string\n");
}

/* DBI ---------------------------------------------------------------------- */
#JL 20130505
//$connstr = "host=localhost dbname=vlab user=postgres password=pq10a129";
$connstr = "host=localhost dbname=vlab user=postgres password=postgres";
#JL

function MyVncPort($VMID) {
    $q = "SELECT vnc_port FROM vlab_interim.vm_resource WHERE vm_id = $VMID;";
    $rows = GetRows($q);
    //there should be only one row.  if not you have BIG PROBLEMS
    //vm_id should be unique
    if (count($rows) != 1)
       die ("MyVncPort: ERROR: multiple vmids in vm_resource!!");
    $answer = $rows[0];
    return($answer['vnc_port']);
}

function VIF($VMID) {
    // $q = "SELECT vif_mac, vif_brdg_id, vif_name FROM vlab_interim.vif WHERE vm_id = $VMID ORDER BY vif_name;";
    $t = "vlab_interim";
    $q = "SELECT vif.vif_mac, vbridge.brdg_name, vif.vif_name FROM $t.vif, $t.vbridge WHERE vm_id = $VMID and vif.vif_brdg_id = vbridge.brdg_id ORDER BY vif_name;";
    $rows = GetRows($q);
    $macs = array_map(
        function($row) {
            $vif_mac  = $row['vif_mac'];
            $vif_name = $row['vif_name'];
            $brdg_name   = $row['brdg_name'];

            //return("'mac=$vif_mac,vifname=$vif_name,bridge=$brdg_name'");
            return("'mac=$vif_mac,bridge=$brdg_name'");
        }, $rows);
    return(implode(',', $macs));
}

function GetClassSize($class)
{
    $q = "SELECT size FROM vlab_interim.counter WHERE name = '$class'";
    $rows = GetRows($q);
    if (count($rows) != 1)
       die ("GetClassSize: ERROR: multiple sizes for a class in counter!!");
    $answer = $rows[0];
    return($answer['size']);
}

function MakeNewQcowFromBase() {
    //$q = "SELECT vm_name_base, vnc_base, conf_template, course, id, vm_friendly_name, reimage_file, disk_base_name, memory WHERE createdp=false";
    $q = "SELECT vm_name_base, id, reimage_file, backing_file from vlab_interim.vnc_base WHERE createdp=false";
    $NewBases = GetRows($q);

    $size = count($NewBases);
    l("There are $size new bases to make in vnc_base");

    if ($size == 0) return(false);
    else {
        array_map(function($x) {
	    MakeSingleQcow($x);
            MarkCreated('vlab_interim.vnc_base', array('id' => $x['id']));
        }, $NewBases);
        return(true);
    }
}

function MakeUnborn() {
    $query
        = "SELECT * from vlab_interim.vm_resource WHERE createdp=false;";
    $unborns = GetRows($query);

    $size = count($unborns);
    l("There are $size machines to make in vm_resource!");

    if ($size == 0) return(false);
    else {
        pg_query(MyDB::Get()->Connection(), "BEGIN");
        array_map(function($x) {
            global $PATH;
            global $GENERIC_TEMPLATE;

            MakeTemplate($GENERIC_TEMPLATE, $x);
            CopyQcow($x['reimage_file'], $x['disk_image']);

            MarkCreatedAndEnabled('vlab_interim.vm_resource',
                        array('vm_id' => $x['vm_id']));}, $unborns);
        pg_query(MyDB::Get()->Connection(), "COMMIT");
        return(true);
    }
}

function GetRows($query) {
    $answer = AskTheDB($query);
    $rows = Array();
    $i = 0;
    while ($row = pg_fetch_assoc($answer)) {
        $rows[$i] = $row;
        $i += 1;
    }
    //l("GetRows: ".$query." returning ".count($rows)." rows");
    return($rows);
}


/* TODO: is this faster if you're only matching part of the row? */
function MarkCreated($table, $constraint) {
    $data = array("createdp" => "true");
    $res = pg_update(MyDB::Get()->Connection(), $table, $data, $constraint)
        or die ('MarkCreated: Update failed '.pg_last_error());
    if ($res) l("row updated");

}

function MarkCreatedAndEnabled($table, $constraint) {
    $data = array("createdp" => "true", "disabled" => "0");
    $res = pg_update(MyDB::Get()->Connection(), $table, $data, $constraint)
        or die ('MarkCreatedAndEnabled: Update failed '.pg_last_error());
    if ($res) l("row updated");

}

function AskTheDB($query)
{
    $dbResult = pg_query(MyDB::Get()->Connection(), $query)
        or die ('Query failed '.pg_last_error());
    return($dbResult);
}

class MyDB {
    private static $Instance;
    private static $i = 0;

    private $DBC;

    public function Connection() {
        return $this->DBC;
    }

    private function __construct() {
        global $connstr;
        //dp("MyDB::__construct");
        $this->DBC = pg_connect($connstr) or die ("pg_connect failed");
        $this->i += 1;
        if ($this->i > 1) die ("Instance singleton is not single.");
    }
	function __destruct() {
        global $DEBUG;
        //dp("MyDB::__destruct");
		l("closing db connection");
        if ($DEBUG and $this->i > 1) die ("singleton is not single!!");
        pg_close($this->DBC);
	}

	public static function Get()
	{
        if (!isset(self::$Instance)) {
			l("creating a new db connection.");
            $className = 'MyDB';
            self::$Instance = new $className;
        }

        return(self::$Instance);
	}
}

/* Main --------------------------------------------------------------------- */

$PATH = "/home/vlab_scp/vm_conf/manual_configs";
$GENERIC_TEMPLATE = "$PATH/generic.template";


function MakeTemplate($file, $info) {
    global $PATH;
    //$PATH = $DEBUG ? "/tmp" : "/home/vlab_scp/vm_conf/manual_configs";
    //dp("in MakeTemplate");

    $fd = fopen($file, 'r');
    $template = fread($fd, filesize($file));
    fclose($fd);

    $out = str_replace('VM_MEMORY', $info['memory'],     $template);
    $out = str_replace('VM_DISK',   $info['disk_image'], $out);
    $out = str_replace('VM_NAME',   $info['vm_name'],    $out);
#    $out = str_replace('VCPU',   $info['vcpu'],    $out);
    $out = str_replace('VM_VNC', MyVncPort($info['vm_id']), $out);
    $out = str_replace('VM_VIF', VIF($info['vm_id']), $out);

    $outputfile = "$PATH/". $info['vm_name'] . ".conf";
    l("making $outputfile");
    file_put_contents($outputfile, $out);
}

/* TODO: move $X to conf */

function CopyQcow($src, $dst) {
    $X = "/home/vmdsk";
    $cmd = "ssh Vlab-xen1 cp $X/$src $X/$dst";
    l("running: $cmd");
    system($cmd);
}

function MakeSingleQcow($row) {
    $ImgDir = "/home/local_blade/OS_images";
    $X = "/home/vmdsk";
    $BaseName = $row['backing_file'];
    $NewFile  = $row['reimage_file'];
    l("Newfile: $NewFile");

    $PathArr = explode("/", $NewFile);
    //print_r($PathArr);
    $cmd = "ssh Vlab-xen1 mkdir $X/$PathArr[0]";
    l("running: $cmd");

    system($cmd);
    $cmd = "ssh Vlab-xen1 /usr/bin/qemu-img create -b $ImgDir/$BaseName -f qcow2 $X/$NewFile 200M" ;
    //TODO make this actually system not log
    l("running: $cmd");
    system($cmd);
    //dp("marking created in db");
}


function ProcessDB() {
    global $t;
   	pg_query(MyDB::Get()->Connection(), "BEGIN");
    pg_query("insert into $t.reflector (vm_id, reflector_port)(select vm_id, vnc_port+10000 as reflector_port from $t.vm_resource where createdp='false');");
    pg_query("insert into $t.user_reflector (user_id, reflector_id) (select $t.\"user\".user_id, $t.reflector.reflector_id from $t.reflector, $t.vm_resource, $t.\"user\" where $t.reflector.vm_id = $t.vm_resource.vm_id and $t.\"user\".user_name = $t.vm_resource.username and $t.vm_resource.createdp = 'false');");

    if (MakeNewQcowFromBase()) {
        MakeUnborn();
    }
    pg_query(MyDB::Get()->Connection(), "COMMIT");
}

function ClassesToBeDeleted() {
    global $t;
    $q = "SELECT id FROM $t.class WHERE deletep = true";
    $rows = GetRows($q);
    $sz = count($rows);
    $ret = array();
    for ($i=0; $i<$sz; $i++) {
        $ret[$i] = $rows[$i]['id'];
    }
    return($ret);
}

function ClassesToBeRestored() {
    global $t;
    $q = "SELECT id FROM $t.class WHERE restorep = true";
    $rows = GetRows($q);
    $sz = count($rows);
    $ret = array();
    for ($i=0; $i<$sz; $i++) {
        $ret[$i] = $rows[$i]['id'];
    }
    return($ret);
}

function writeln($str) { print("$str\n"); }

function LogAndSys($cmd) { l($cmd); system($cmd); }
function LogAndDBI($cmd) { l($cmd); AskTheDB($cmd); }

$DiskFilesBaseDir = '/home/vmdsk';
$ConfFilesBaseDir = '/home/vlab_scp/vm_conf/manual_configs';
function DeleteDiskFiles($dict) {
    global $DiskFilesBaseDir;
    global $ConfFilesBaseDir;
    $tmp  = explode('/', $dict['reimage_file']);
    $dir  = $tmp[0];
    $ReimageFile = $dict['disk_image'];
    $ConfFile = $dict['vm_name' ] . '.conf';
    $cmds = array();
#    $cmds[0] = "ssh vlab-bld-y2 rm -rf $DiskFilesBaseDir/$dir";
#    $cmds[1] = "ssh vlab-bld-y2 rm -rf $DiskFilesBaseDir/$ReimageFile";
#    $cmds[2] = "ssh vlab-bld-y2 rm -rf $ConfFilesBaseDir/$ConfFile";
# Don't delete, make an archive... Sorry Joel!
    $cmds[0] = "ssh Vlab-xen1 mv $DiskFilesBaseDir/$dir $DiskFilesBaseDir/backup/$dir";
    $cmds[1] = "ssh Vlab-xen1 mv $DiskFilesBaseDir/$ReimageFile $DiskFilesBaseDir/backup/$dir";
    $cmds[2] = "ssh Vlab-xen1 mv $ConfFilesBaseDir/$ConfFile $ConfFilesBaseDir/$ConfFile/backup/$dir/";
    array_map('LogAndSys', $cmds);
}

function DeleteAClass($id, $restore) {
    global $t;
    l("deleting class $id\n");
    $cols = "vm_name, vm_id, vm_friendly_name, disk_image, reimage_file, createdp";
    $q = array();
    $q[0] = "SELECT class FROM $t.class WHERE id=$id";
    $q[1] = "SELECT reimage_file FROM $t.vnc_base WHERE course=($q[0])";
    //print_r(GetRows($q[1]));


    $q[2] = "SELECT $cols from $t.vm_resource WHERE reimage_file in ($q[1]);";
    $ans = GetRows($q[2]);
    //print_r($ans);
    array_map('DeleteDiskFiles', $ans);

    $sql = array();
    $sql[0] = "delete from $t.vbridge where brdg_id in (select vif_brdg_id from $t.vif where vm_id in (select vm_id from $t.vm_resource where reimage_file in (select reimage_file from $t.vnc_base where course = ($q[0]))));";
    $sql[1] = "delete from $t.vif where vm_id in (select vm_id from $t.vm_resource where reimage_file in (select reimage_file from $t.vnc_base where course = ($q[0])))";
    $sql[2] = "delete from $t.vm_resource where reimage_file in (select reimage_file from $t.vnc_base where course = ($q[0]));";
    $sql[3] = "delete from $t.reflector where vm_id not in (select vm_id from $t.vm_resource);";
    $sql[4] = "delete from $t.user_reflector where reflector_id not in (select reflector_id  from $t.reflector);";
    $sql[5] = "delete from $t.vnc_base where course = ($q[0]);";
    if ($restore) {
        $sql[6] = "update $t.class set restorep=false where id=$id;";
    } else {
        $sql[6] = "delete from $t.counter where name =($q[0]);";
        $sql[7] = "delete from $t.class where deletep = true;";
        $sql[8] = "delete from $t.email where course_id=$id;";
    }

    //array_map(function($x) { print($x."\n"); }, $sql);
    array_map('LogAndDBI', $sql);


}

function ProcessDel() {
    global $t;
    pg_query(MyDB::Get()->Connection(), "BEGIN");
    $list = ClassesToBeDeleted();
    //array_map('DeleteAClass', $list);
    foreach ($list as $item) {
        DeleteAClass($item, false);
    }
    pg_query(MyDB::Get()->Connection(), "COMMIT");
}

function ProcessRes() {
    global $t;
    pg_query(MyDB::Get()->Connection(), "BEGIN");
    $list = ClassesToBeRestored();
    foreach ($list as $item) {
        DeleteAClass($item, true);
    }
    pg_query(MyDB::Get()->Connection(), "COMMIT");
}

?>
