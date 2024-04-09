<?php

namespace IPP\Student\Literals;

abstract class SymbType {
    const INT = 1;
    const STRING = 2;
}

class Symbol {
    private $value;
    private $type;

    public function __construct(string $type, string $value) {
        $this->value = $value;
        $this->type = $type;
    }

    public function getValue() {
        return $this->value;
    }

    public function getType() {
        return $this->type;
    }
}

class Variable extends Symbol{
    private $name;
    private $scope;

    public function __construct(string $variable) {
        [$scope, $name] = $this->getScopeAndName($variable);
        
        $this->scope = $scope;
        $this->name = $name;
    }

    public function assign(string $type, string $value){
        parent::__construct($value, $type);
    }

    public function getName() {
        return $this->name;
    }

    public function getScope() {
        return $this->scope;
    }

    private function getScopeAndName(string $variable) {
        $success = explode('@', $variable);

        if(!$success){
            // TODO error
        }

        return $success;
    }
}