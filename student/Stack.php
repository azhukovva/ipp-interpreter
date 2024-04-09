<?php

namespace IPP\Student;

use IPP\Student\Literals\Variable;

interface IStack {
    public function declareVariable(Variable $variable);
    public function getVariable(Variable $variable);
}

class Frame implements IStack {
    private $data;

    public function __construct()
    {
        $this->data = array();
    }

    private function has(string $name): bool {
        return array_key_exists($name, $this->data);
    }

    public function getVariable(Variable $variable): Variable {
        $name = $variable->getName();

        if(!$this->has($name)) {
            // TODO Throw error: Variable undefined
        }

        return $this->data[$name];
    }

    public function declareVariable(Variable $variable): void {
        $name = $variable->getName();

        if($this->has($name)) {
            // TODO Throw error: Variable already exists
        }

        $this->data[$name] = $variable;
    }
}

class Stack implements IStack {
    private $gframe;
    private $tframe;
    private $lframe;

    public function __construct()
    {
        $this->gframe = new Frame;
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
                    // TODO error: local frames were not created
                }

                end($this->lframe)->declareVariable($variable);
                break;
            case "TF":
                if(!isset($this->tframe)) {
                    //TODO error: tframe was not created
                }

                $this->tframe->declareVariable($variable);
                break;
            default:
                // TODO error: unexpected scope
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
                    // TODO error: local frames were not created
                }

                return end($this->lframe)->getVariable($variable);
            case "TF":
                if(!isset($this->tframe)) {
                    //TODO error: tframe was not created
                }

                return $this->tframe->getVariable($variable);
            default:
                // TODO error: unexpected scope
        }
    }

}