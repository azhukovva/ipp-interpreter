<?php

namespace IPP\Student\Literals;

use IPP\Student\HelperFunctions;
use IPP\Core\ReturnCode;

abstract class SymbType
{
    const INT = 1;
    const STRING = 2;
}

class Symbol
{
    private $value;
    private $type;

    public function __construct(string $type, string $value)
    {
        $this->type = $type;


        switch ($this->type) {
            case "int":
                $this->value = intval($value);
                break;
            case "string":
            case "bool":
                $this->value = $value;
                break;
            case "nil":
                $this->value = "nil";
                break;
            default:
                // REVIEW error type
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
        }
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getType()
    {
        return $this->type;
    }
}

class Variable extends Symbol
{
    private $name;
    private $scope;

    public function __construct(string $variable)
    {
        $parts = $this->getScopeAndName($variable);
        $this->scope = $parts[0] ?? null;
        $this->name = $parts[1] ?? null;
    }

    public function assign(string $type, string $value)
    {
        parent::__construct($type, $value);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getScope()
    {
        return $this->scope;
    }

    private function getScopeAndName(string $variable)
    {
        $success = explode('@', $variable);

        if (!$success) {
            //REVIEW error
            HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
        }
        return $success;
    }
}
