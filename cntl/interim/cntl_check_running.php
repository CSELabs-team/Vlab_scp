<?php
// http://stackoverflow.com/questions/1656350/php-check-process-id

class instance {

    private $lock_file = '';
    private $is_running = false;

    public $logger;

    public function __construct($session_name = __FILE__) {

	$this->logger =& Logger::getLogger(get_class($this));
        $id = md5($session_name);

        $this->lock_file = sys_get_temp_dir() . '/' . $session_name . '.' . $id;

        if (file_exists($this->lock_file)) {
                $this->is_running = true;
        } else {
                $file = fopen($this->lock_file, 'w');
                fclose($file);
        }
    }

    public function __destruct() {
        if (file_exists($this->lock_file) && !$this->is_running) {
                unlink($this->lock_file);
        }
    }

    public function is_running() {
        return $this->is_running;
    }

}

$instance = new instance('vlab_cntl'); // the argument is optional as it defaults to __FILE__

if ($instance->is_running()) {
    echo 'Controller already running';        
    $instance->logger->debug('Controller already running');        
    exit ('Controller already running');
} else {
    echo 'Multiple controller running check passed';
}

?>
