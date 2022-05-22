<?php

function IPS_LogMessage($message) {
    echo $message."\n";
}

class IPSModule {
    public $status = 0;

    public function __construct($InstanceID) {
    }

    public function Create() {
    }

    public function ApplyChanges() {
    }

    public function ReadPropertyBoolean($key) {
        return true;
    }
    public function ReadPropertyInteger($key) {
        return 1;
    }
    public function ReadPropertyString($key) {
        switch ($key) {
            default:
                return '';
                break;
        }
    }
    public function SetStatus($status) {
        $this->status = $status;
    }

}

require_once ('./module.php');
$module = new CSAutoLightControl();
$module->isDebug = true;
//$module->showData();
$module->StartController();