<?php
    /*
     * (C)opyright 2018, Cool Smart UG, All Rights Reserved
     * www.coolsmart.de
     */

    class CSAutoLightControl extends IPSModule {

        const maxInput = 5;
        const maxOutput = 5;
        const EVENT_CONTROLLER = 'CSEV_EVENT_CONTROLLER';
        public $isDebug = false;
        private $instanceId = null;
        private $name = '';

        private function log($message) {
            if ($this->isDebug) {
                echo "LOG: AutoLight: ".$message."\n";
            } else {
                $logging = $this->ReadPropertyInteger("ENABLE_LOGGING");
                if ($logging) {
                    IPS_LogMessage("AUTO LIGHT", "[" . IPS_GetName($this->InstanceID) . "] " . $message);
                }
            }
        }

        public function __construct($InstanceID=NULL) {
            parent::__construct($InstanceID);
            $this->instanceId = $InstanceID;
            $this->name = IPS_GetName($this->instanceId);
        }

        public function Create()
        {
            parent::Create();

            for ($i=1; $i<= self::maxInput; $i++) {
                $this->RegisterPropertyInteger('INPUT_TRIGGER_'.$i, 0);
            }
            for ($i=1; $i<= self::maxOutput; $i++) {
                $this->RegisterPropertyInteger('OUTPUT_'.$i, 0);
            }
            $this->RegisterPropertyInteger('DURATION', 1);
            $this->RegisterPropertyInteger('ENABLE_CONDITION', 0);
            $this->RegisterPropertyInteger('ENABLE_LOGGING', 0);
            $this->RegisterTimer('CSSL_OFF_TIMER', 0, "CSSL_StopController(\$_IPS['TARGET']);");
	    $this->RegisterVariableBoolean('Active', $this->Translate("Auto"), "~Switch");
	    $this->RegisterPropertyString('SCENE1', '');
	    $this->RegisterPropertyString('SCENE2', '');
            $this->EnableAction('Active');
        }

        protected function CreateEvent($name) {
            if ($name) {
                $this->log('CreateEvent '.$name);
                $id = IPS_CreateEvent(0);
                IPS_SetParent($id, $this->InstanceID);
                IPS_SetName($id, "Event");
                IPS_SetIdent($id, $name);
                IPS_SetEventActive($id, false);
                IPS_SetEventTriggerSubsequentExecution($id, true);
                IPS_SetEventScript($id, "CSSL_StartController(\$_IPS['TARGET'], false);");
            }
            return $id;
        }

        protected function ApplyChangesItem($name, $triggerId) {
            $this->log('ApplyChangesItem name='.$name." trigger=".$triggerId. " instance=". $this->InstanceID);
            $eventId = @IPS_GetObjectIDByIdent($name, $this->InstanceID);
            $this->log('  eventId = '.$eventId);
            if ($eventId === false){
                $eventId = $this->CreateEvent($name);
            }
            IPS_SetEventActive($eventId, !($triggerId == 0));
            if ($triggerId) {
                IPS_SetEventTrigger($eventId, 4, $triggerId);
                $variable = IPS_GetVariable($triggerId);
                switch ($variable['VariableType']) {
                    case 0:
                        $this->log('  is boolean');
                        IPS_SetEventTriggerValue($eventId, true);
                        break;
                    default:
                        $this->log('  is integer or float');
                        IPS_SetEventTriggerValue($eventId, 1);
                        break;
                }
            }

            $enableConditionId = $this->ReadPropertyInteger('ENABLE_CONDITION');
            if ($enableConditionId) {
                $variable = IPS_GetVariable($enableConditionId);
                $dataType = $variable['VariableType'];
                switch ($dataType) {
                    case 0:
                        $conditionValue = true;
                        break;
                    case 1:
                    case 2:
                        $conditionValue = 1;
                        break;
                    case 3:
                        $conditionValue = '1';
                        break;
                }
                IPS_SetEventCondition($eventId, 0, 0, 0);
                IPS_SetEventConditionVariableRule($eventId, 0, 0, $enableConditionId, 0, $conditionValue);
            } else {
                $event = IPS_GetEvent($eventId);
                $conditions = $event['EventConditions'];
                if (is_array($conditions) && count($conditions)) {
                    IPS_SetEventCondition($eventId, 0, -1, 0);
                }
            }
        }

        public function ApplyChanges() {
            parent::ApplyChanges();
            $this->log('ApplyChangesItem ');

            for ($i=1; $i<= self::maxInput; $i++) {
                $event = 'EVENT'.$i;
                $inputTrigger = "INPUT_TRIGGER_".$i;

                $triggerId = $this->ReadPropertyInteger($inputTrigger);
                $this->ApplyChangesItem($event, $triggerId);
            }
        }

        public function RequestAction($ident, $value) {
            $this->log('RequestAction '.$ident);
            switch($ident) {
                case "Active":
                    $this->SetActive($value);
                    break;
                default:
                    throw new Exception("Invalid ident");
            }
        }

        public function SetActive($value) {
            $this->log('SetActive value='.(int)$value);
            SetValue($this->GetIDForIdent("Active"), $value);
        }

        public function StartController(){
            if (!GetValue($this->GetIDForIdent("Active"))){
                $this->log('Controller not active');
                return;
            }

            $duration = $this->ReadPropertyInteger("DURATION");
            $this->log('StartController '.$duration.'min');
            $this->SetTimerInterval("CSSL_OFF_TIMER", $duration * 60 * 1000);

            $this->SwitchItems(true);

            try {
                $eventInstanceId = IPS_GetObjectIDByIdent(self::EVENT_CONTROLLER, 0);
                if ($eventInstanceId) {
                    CSEV_AddEvent($eventInstanceId, $this->name, 'EVENT_LIGHT', $this->Translate('On'), null, null);
                }
            } catch (Exception $e) {
            }
        }

        public function StopController(){
            $this->log('StopController');

            for ($i=1; $i<= self::maxInput; $i++) {
                $inputTrigger = "INPUT_TRIGGER_".$i;

                $triggerId = $this->ReadPropertyInteger($inputTrigger);
                $status = GetValue($triggerId);
                if ($status) {
                    $this->log('INPUT is still active '.$triggerId." ".(int)$status);
                    $this->SetTimerInterval("CSSL_OFF_TIMER", 0);
                    $duration = $this->ReadPropertyInteger("DURATION");
                    $this->SetTimerInterval("CSSL_OFF_TIMER", $duration * 60 * 1000);
                    return 0;
                } else {
                    $this->log('INPUT not active '.$triggerId." ".(int)$status);
                }
            }

            $this->SwitchItems(false);
            $this->SetTimerInterval("CSSL_OFF_TIMER", 0);

            try {
                $eventInstanceId = IPS_GetObjectIDByIdent(self::EVENT_CONTROLLER, 0);
                if ($eventInstanceId) {
                    CSEV_AddEvent($eventInstanceId, $this->name, 'EVENT_LIGHT', $this->Translate('Off'), null, null);
                }
            } catch(Exception $e) {}
        }

        private function CheckForScript($objectId) {
            $object = IPS_GetObject($objectId);
            if ($object['HasChildren']) {
                $childrenIds = $object['ChildrenIDs'];
                foreach ($childrenIds as $childrenId) {
                    $childrenObject = IPS_GetObject($childrenId);
                    if ($childrenObject['ObjectType'] == 3) {
                        return $childrenId;
                    }
                }
            }
            return false;
        }

        private function StartVariableScript($scriptId, $value) {
            $result = IPS_RunScriptWaitEx($scriptId, Array('VALUE' => $value));
            return $result;
        }

        private function SwitchItem($outputId, $value) {
            if (!$outputId) { return false; }

            $object = IPS_GetObject($outputId);
            $dataType = $object['ObjectType'];
            $this->log('SwitchItem id='.$outputId.' type='.$dataType);

            switch ($dataType) {
                case 2:
                    $this->log('Variable');
                    if ($scriptId = $this->CheckForScript($outputId)) {
                        $this->log('Find script '.$scriptId);
                        $this->StartVariableScript($scriptId, $value);
                    } else {
                        SetValue($outputId, $value);
                    }
                    break;
                case 1:
                    $instance = IPS_GetInstance($outputId);
                    $moduleId = $instance['ModuleInfo']['ModuleID'];
                    $module = IPS_GetModule($moduleId);
                    $moduleName = $module['ModuleName'];
                    $this->log('module='.$moduleId.', '.$moduleName);
                    switch ($moduleName) {
		    case 'EIB Group':
		    case 'KNX EIS Group':
                            EIB_Switch($outputId, $value);
                            break;
                        case 'HomeMatic Device':
                            HM_WriteValueBoolean($outputId, 'STATE', $value);
			    break;
			case 'HUEDevice':
				if($value && $this->ReadPropertyString('SCENE1')) {
				  $hour = date('H');
				  if ($hour > 23 && $hour < 8 && $this->ReadPropertyString('SCENE2')) {
		                    $this->log('scene='.$this->ReadPropertyString('SCENE2'));
				    PHUE_SceneSet($outputId, $this->ReadPropertyInteger('SCENE2'));
				  } else {
				    $this->log('scene='.$this->ReadPropertyString('SCENE1'));
				    PHUE_SceneSet($outputId, $this->ReadPropertyString('SCENE1'));
				  }
				} else {
				  $this->log('value='.(int)$value);
				  PHUE_SwitchMode($outputId, $value);
				}
				break;
                    }
                    break;
            }
        }

        private function SwitchItems(bool $value){
            $this->log('SwitchItems '.(int)$value);

            for ($i=1; $i<=self::maxOutput; $i++) {
                $outputId = $this->ReadPropertyInteger("OUTPUT_".$i);
                if ($outputId) {
                    $this->SwitchItem($outputId, $value);
                }
            }
        }
    }

?>
