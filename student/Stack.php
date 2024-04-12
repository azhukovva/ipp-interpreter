<?php

namespace IPP\Student;

use IPP\Core\ReturnCode;
use IPP\Student\Literals\Variable;

interface IStack {
    public function declareVariable(Variable $variable);
    public function getVariable(Variable $variable);
}

class Frame implements IStack {
    private $data; // stores Variable objects

    public function __construct()
    {
        $this->data = array();
    }

    private function has(string $name): bool {
        return array_key_exists($name, $this->data);
    }

     // adds a variable to the data array
     public function declareVariable(Variable $variable): void {
        $name = $variable->getName();
        
        if($this->has($name)) {
            // REVIEW Variable already exists
            HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR);
        }

        $this->data[$name] = $variable;
    }

    // retrieves a variable from the data array
    public function getVariable(Variable $variable): Variable {
        $name = $variable->getName();
 

        if(!$this->has($name)) {
            // REVIEW Throw error: Variable undefined

            HelperFunctions::validateErrorCode(ReturnCode::VARIABLE_ACCESS_ERROR);
        }
        return $this->data[$name];
    }
}

class Stack implements IStack {
    //REVIEW - public??? does not work in createframe case
    private $gframe; // global
    public $tframe; // temporary
    public $lframe; // local

    public function __construct()
    {
        $this->gframe = new Frame;
        $this->tframe = null;
        $this->lframe = array();
    }

    public function declareVariable(Variable $variable): void {
        $scope = $variable->getScope();

        switch($scope) {
            case "GF":
                $this->gframe->declareVariable($variable);
                break;
            case "LF":
                if(empty($this->lframe)){
                    //REVIEW -  error: local frames were not created
                    HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);
                }
                // get the last local frame that was added to the lframe array
                end($this->lframe)->declareVariable($variable);
                break;
            case "TF":
                if(!isset($this->tframe)) {
                    //REVIEW error: tframe was not created
                    HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);
                }

                if ($this->tframe !== null) {
                    $this->tframe->declareVariable($variable);
                }
                break;
            default:
                //REVIEW error: unexpected scope
                HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);

        }
    }

    public function getVariable(Variable $variable): Variable {
        $name = $variable->getName();
        $scope = $variable->getScope();
        switch($scope) {
            case "GF":
                return $this->gframe->getVariable($variable);
            case "LF":
                if(empty($this->lframe)){
                    // REVIEW error: local frames were not created
                    HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);
                }

                return end($this->lframe)->getVariable($variable);
            case "TF":
                if(!isset($this->tframe)) {
                    //REVIEW error: tframe was not created
                    HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);
                }

                if(isset($this->tframe)) {
                    return $this->tframe->getVariable($variable);
                } else {
                    // REVIEW Throw error: Temporary frame was not created
                    HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);
                }
            default:
                // REVIEW error: unexpected scope
                HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR);
        }
    }


}