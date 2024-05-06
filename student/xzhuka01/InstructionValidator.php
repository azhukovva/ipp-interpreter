<?php

namespace IPP\Student;

use IPP\Student\HelperFunctions;
use IPP\Core\ReturnCode;

class InstructionValidator
{
    /**
     * @param array<mixed> $instructionsArray
     */
    public static function validate(array $instructionsArray): void
    {
        $opcode_types = [
            "CREATEFRAME",
            "PUSHFRAME",
            "POPFRAME",
            "RETURN",
            "BREAK",

            "DEFVAR",
            "POPS",
            "CALL",
            "LABEL",
            "JUMP",
            "PUSHS",

            "WRITE",
            "EXIT",
            "DPRINT",
            "MOVE",
            "STRLEN",
            "TYPE",
            "NOT",
            "INT2CHAR",
            "READ",

            "ADD",
            "SUB",
            "MUL",
            "IDIV",

            "LT",
            "GT",
            "EQ",

            "AND",
            "OR",
            "STRI2INT",
            "CONCAT",
            "GETCHAR",
            "SETCHAR",
            "JUMPIFEQ",
            "JUMPIFNEQ",
        ];

        foreach ($instructionsArray as $instruction) {
            $opcode = strtoupper($instruction['opcode']);
            $order = $instruction["order"];

            if(intval($order) <= 0) {
                fwrite(STDERR, "ERROR: order must be > 0\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
            }

            if (!in_array($opcode, $opcode_types)) {
                fwrite(STDERR, "ERROR: Unknown opcode\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            } else {
                $argsList = [];
                foreach ($instruction['arguments'] as $argName => $data) {
                    [$argType, $argValue] = $data;
                    $argsList[] = [$argName, $argType, $argValue];
                }
                self::validateSyntax($opcode, $argsList);
            }
        }
    }

    /**
     * @param array<mixed> $argsList
     */
    public static function validateSyntax(string $opcode, array $argsList) : void
    {
        switch ($opcode) {

            case "CREATEFRAME":
            case "PUSHFRAME":
            case "POPFRAME":
            case "RETURN":
            case "BREAK":
                self::checkArgNumber($argsList, 0);
                break;

            case "DEFVAR":
            case "POPS":
                self::checkArgNumber($argsList, 1);
                $expectedTypes = ["var"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;
            case "CALL":
            case "LABEL":
            case "JUMP":
                self::checkArgNumber($argsList, 1);
                $expectedTypes = ["label"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;
            case "PUSHS":
            case "WRITE":
            case "EXIT":
            case "DPRINT":
                self::checkArgNumber($argsList, 1);
                $expectedTypes = ["symb"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;


            case "MOVE":
            case "INT2CHAR":
            case "STRLEN":
            case "TYPE":
            case "NOT":
                self::checkArgNumber($argsList, 2);
                $expectedTypes = ["var", "symb"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;
            case "READ":
                self::checkArgNumber($argsList, 2);
                $expectedTypes = ["var", "type"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;


            case "ADD":
                self::checkArgNumber($argsList, 3);
                $expectedTypes = ["var", "symb", "symb"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;
            case "SUB":
            case "MUL":
            case "IDIV":
            case "LT":
            case "GT":
            case "EQ":
            case "AND":
            case "OR":
            case "STRI2INT":
            case "CONCAT":
            case "GETCHAR":
            case "SETCHAR":
                self::checkArgNumber($argsList, 3);
                $expectedTypes = ["var", "symb", "symb"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;

            case "JUMPIFEQ":
            case "JUMPIFNEQ":
                self::checkArgNumber($argsList, 3);
                $expectedTypes = ["label", "symb", "symb"];
                self::checkArgType($argsList, $expectedTypes, $opcode);
                break;


            default:
                fwrite(STDERR, "Unknown opcode: $opcode\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
                break;
        }
    }

    /**
     * @param array<mixed> $argsList
     */
    private static function checkArgNumber(array $argsList, int $expectedCount) : void
    {
        if (count($argsList) != $expectedCount) {
            fwrite(STDERR, "ERROR: Invalid number of arguments\n");
            HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
    }

     /**
     * @param array<mixed> $argsList
     * @param array<mixed> $expectedTypes
     */
    private static function checkArgType(array $argsList, array $expectedTypes, string $opcode) : void
    {
        $types = [];
        foreach ($argsList as $innerArray) {
            $types[] = $innerArray[1]; 
        }

        for ($i = 0; $i < count($types); $i++) {
            if ($opcode == "ADD" || $opcode == "SUB" || $opcode == "MUL" || $opcode == "IDIV") {
                for ($i = 1; $i < count($types); $i++) {

                    if ($types[$i] == "int" && !is_numeric($argsList[$i][2])) {
                        fprintf(STDERR, "ERROR: Invalid integer value\n");
                        HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
                    }

                    if ($types[$i] == "var" || $types[$i] == "int") {
                        $types[$i] = "symb";
                    } else if ($types[$i] == "nil") {
                        fprintf(STDERR, "ERROR: Invalid argument type $types[$i]\n");
                        HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                    }
                }
            }

            if (
                $opcode == "MOVE" || $opcode == "WRITE" || $opcode == "JUMPIFEQ" || $opcode == "JUMPIFNEQ" || $opcode == "CONCAT" ||
                $opcode == "GETCHAR" || $opcode == "SETCHAR" || $opcode == "NOT" || $opcode == "AND" || 
                $opcode == "LT" || $opcode == "GT" || $opcode == "EQ" ||
                $opcode == "INT2CHAR" || $opcode == "STRI2INT" ||
                $opcode == "PUSHS" || $opcode == "EXIT" || $opcode == "CONCAT" || $opcode == "TYPE"
            ) {
                for ($i = 0; $i < count($types); $i++) {
                    if ($types[$i] == "var" || $types[$i] == "string" || $types[$i] == "bool" || $types[$i] == "int" || $types[$i] == "nil") {
                        $types[$i] = "symb";
                    }
                }
            }
        }
        if (($opcode == "MOVE" || $opcode == "SETCHAR" || $opcode == "NOT" || $opcode == "AND"
            || $opcode == "LT" || $opcode == "GT" || $opcode == "EQ" ||
            $opcode == "INT2CHAR" || $opcode == "STRI2INT" || $opcode == "GETCHAR" ||
            $opcode == "CONCAT" || $opcode == "TYPE") && $types[0] == "symb") {
            $types[0] = "var";
        }

        if ($opcode == "STRLEN") {
            for ($i = 0; $i < count($types); $i++) {
                if ($types[1] == "var" || $types[1] == "string" || $types[1] == "bool" || $types[1] == "int" || $types[1] == "nil") {
                    $types[$i] = "symb";
                }
            }
            $types[0] = "var";
        }
        if ($opcode == "OR") {
            for ($i = 0; $i < count($types); $i++) {
                if ($types[$i] == "var" || $types[$i] == "string" || $types[$i] == "bool" || $types[$i] == "int" || $types[$i] == "nil") {
                    $types[$i] = "symb";
                }
            }
            $types[0] = "var";
        }
    }
}
