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
                self::checkArgNumber($argsList, 1);
                break;
            case "CALL":
                self::checkArgNumber($argsList, 1);
                break;
            case "PUSHS":
                self::checkArgNumber($argsList, 1);
                break;
            case "POPS":
                self::checkArgNumber($argsList, 1);
                break;
            case "LABEL":
                self::checkArgNumber($argsList, 1);
                break;
            case "JUMP":
                self::checkArgNumber($argsList, 1);
                break;
            case "WRITE":
                self::checkArgNumber($argsList, 1);
                break;
            case "EXIT":
                self::checkArgNumber($argsList, 1);
                break;
            case "DPRINT":
                self::checkArgNumber($argsList, 1);
                break;


            case "MOVE":
                self::checkArgNumber($argsList, 2);
                break;
            case "INT2CHAR":
                self::checkArgNumber($argsList, 2);
                break;
            case "READ":
                self::checkArgNumber($argsList, 2);
                break;
            case "STRLEN":
                self::checkArgNumber($argsList, 2);
                break;
            case "TYPE":
                self::checkArgNumber($argsList, 2);
                break;


            case "ADD":
                // ADD LF@result 12 14  
                // argsList = [[arg1, var, LF@result], [arg2, int, 12], [arg3, int, 14]]
                self::checkArgNumber($argsList, 3);
                $expectedTypes = ["var", "symb", "symb"];
                self::checkArgType($argsList, $expectedTypes);
                break;
            case "SUB":
                self::checkArgNumber($argsList, 3);
                break;
            case "MUL":
                self::checkArgNumber($argsList, 3);
                break;
            case "IDIV":
                self::checkArgNumber($argsList, 3);
                break;
            case "LT":
                self::checkArgNumber($argsList, 3);
                break;
            case "GT":
                self::checkArgNumber($argsList, 3);
                break;
            case "EQ":
                self::checkArgNumber($argsList, 3);
                break;
            case "AND":
                self::checkArgNumber($argsList, 3);
                break;
            case "OR":
                self::checkArgNumber($argsList, 3);
                break;
            case "NOT":
                self::checkArgNumber($argsList, 3);
                break;
            case "STRI2INT":
                self::checkArgNumber($argsList, 3);
                break;
            case "CONCAT":
                self::checkArgNumber($argsList, 3);
                break;
            case "GETCHAR":
                self::checkArgNumber($argsList, 3);
                break;
            case "SETCHAR":
                self::checkArgNumber($argsList, 3);
                break;
            case "JUMPIFEQ":
                self::checkArgNumber($argsList, 3);
                break;
            case "JUMPIFNEQ":
                self::checkArgNumber($argsList, 3);
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

    private static function checkArgType($argsList, $expectedTypes)
    {
        // argsList = [[arg1, var, LF@result], [arg2, int, 12], [arg3, int, 14]]
        $types = [$argsList[0][1], $argsList[1][1], $argsList[2][1]];

        // types - ["var", "int", "int"]
        // expectedTypes = ["var", "symb", "symb"]

        for ($i = 0; $i < count($types); $i++) {

            if ($types[$i] == "int") {
                $types[$i] = "symb";
            }



            if ($types[$i] != $expectedTypes[$i]) {
                fwrite(STDERR, "ERROR: Invalid argument type\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
        }
    }
}
