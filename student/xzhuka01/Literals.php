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
    /** @var int|string|bool|null $value */
    private $value;
    private string $type;

    /**
     * @param int|string|bool|null $value
     */
    public function __construct(string $type, $value)
    {
        $this->type = $type;
        $this->value = $value;

        switch ($this->type) {
            case "int":
                $this->value = is_numeric($value) ? intval($value) : null;
                break;
            case "var":
            case "string":
            case "bool":
                $this->value = $value;
                break;
            case "nil":
                $this->value = "";
                break;
            default:
                fwrite(STDERR, "ERROR: Invalid argument type $type\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
        }
    }

     /**
     * @return int|string|bool|null
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

class Variable extends Symbol
{
    private string $name;
    private string $scope;

    public function __construct(string $variable)
    {
        $parts = $this->getScopeAndName($variable);
        $this->scope = $parts[0] ?? null;
        $this->name = $parts[1] ?? null;
    }

    /**
     * @param mixed $value
     */
    public function assign(string $type, $value) : void
    {
        parent::__construct($type, $value);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

     /**
     * @return string[]
     */
    private function getScopeAndName(string $variable) : array
    {
        $success = explode('@', $variable);

        if (count($success) < 2) {
            fwrite(STDERR, "ERROR: Invalid variable name\n");
            HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
        }

        return $success;
    }
}
