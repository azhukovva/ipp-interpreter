<?php

namespace IPP\Student;

use IPP\Student\HelperFunctions;
use IPP\Core\ReturnCode;

class InstructionValidator
{
    public static function validate($instructionsArray)
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
            // ADD LF@result 12 14  
            $opcode = strtoupper($instruction['opcode']);
            $order = $instruction["order"];

            if(intval($order) <= 0) {
                fwrite(STDERR, "ERROR: order must be > 0\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
            }
            //REVIEW - $opcode = strtoupper($instruction[0]);

            if (!in_array($opcode, $opcode_types)) {
                fwrite(STDERR, "ERROR: Unknown opcode\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            } else {
                // list of arguments for the current instruction
                $argsList = [];
                // $argName - name of the argument, 
                // $data - the associated data for that argument
                foreach ($instruction['arguments'] as $argName => $data) {
                    [$argType, $argValue] = $data;
                    // [ arg1, var, LF@result ]
                    $argsList[] = [$argName, $argType, $argValue];
                }
                self::validateSyntax($opcode, $argsList);
            }
        }
    }

    public static function validateSyntax($opcode, $argsList)
    {
        // if ($opcode == "CREATEFRAME" || $opcode == "PUSHFRAME" || $opcode == "POPFRAME" || $opcode == "RETURN" || $opcode == "BREAK") {
        //     self::checkArgNumber($argsList, 0);
        // }


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
                // ADD LF@result 12 14  
                // argsList = [[arg1, var, LF@result], [arg2, int, 12], [arg3, int, 14]]
                //NOTE -  add to the other instructions's cases down below
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

    private static function checkArgNumber($argsList, $expectedCount)
    {
        if (count($argsList) != $expectedCount) {
            fwrite(STDERR, "ERROR: Invalid number of arguments\n");
            HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
    }

    private static function checkArgType($argsList, $expectedTypes, $opcode)
    {
        // argsList = [[arg1, var, GF@var], [arg2, int, 20], [arg3, int, 10]]
        // print_r($argsList);
        foreach ($argsList as $innerArray) {
            $types[] = $innerArray[1]; // [ var, int, int ]
        }

        // types - ["var", "int", "int"]
        // expectedTypes = ["var", "symb", "symb"]

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

                    // REVIEW - in interpreter.php - will the division by zero check work?
                    // if ($opcode == "IDIV" && isset($argsList[$i][2]) && $argsList[$i][2] == 0) {
                    //     fprintf(STDERR, "ERROR: Division by zero\n");
                    //     HelperFunctions::validateErrorCode(ReturnCode::OPERAND_VALUE_ERROR); // 57
                    // }

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
                    //REVIEW - $types[$i] == "string"
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




        // if ($types != $expectedTypes) {
        //     print_r($types);
        //     // print_r(" --- \n");
        //     print_r($expectedTypes);
        //     fwrite(STDERR, "ERROR: Invalid argument type \n");
        //     HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
        // }
    }
}
