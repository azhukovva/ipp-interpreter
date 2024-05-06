<?php

namespace IPP\Student;

require_once 'Literals.php';

use IPP\Core\AbstractInterpreter;
use IPP\Student\Literals\Symbol;
use IPP\Student\Literals\Variable;
use IPP\Core\ReturnCode;

class Interpreter extends AbstractInterpreter
{
     // Stack with frames
     private Stack $stack;
     /** @var string[] */
     private array $callStack;
     /** @var string[] */
     private array $dataStack;
     /**
     * @var array<int, array<string, int>>
     */
     private array $labels;

    private function getSymbol(string $type, string $value): Symbol
    {
        if ($type == "var") {
            return $this->stack->getVariable(new Variable($value));
        }
        return new Symbol($type, $value);
    }


    public function execute(): int
    {
        $this->stack = new Stack;
        $this->callStack = array();
        $this->labels = array();
        $this->dataStack = array();

        $dom = $this->source->getDOMDocument();

        $dom->preserveWhiteSpace = false;

        /**
         * After preserveWhiteSpace was set to false, we need to rebuild  xml again
         */
        $dom->loadXml($dom->saveXML());

        // INSTRUCTIONS's array
        $instructions = ParserXML::parseXML($dom);
        InstructionValidator::validate($instructions);

        // Sort instructions by order
        usort($instructions, function($a, $b) {
            $orderA = (int)$a["order"];
            $orderB = (int)$b["order"];
        
            if ($orderA == $orderB) {
                return 0;
            }
            return ($orderA < $orderB) ? -1 : 1;
        });

        // Get all LABEL instructions
        $labelInstructions = array_filter($instructions, function ($item) {
            return $item["opcode"] == "LABEL";
        });
        
        $labelKeys = array();
        $this->labels = array_map(function ($item, $i) use (&$labelKeys) {

            $arguments = $item['arguments'];

            ["arg1" => [0 => $type, 1 => $labelKey]] = $arguments;

            if(in_array($labelKey, $labelKeys)){
                fwrite(STDERR, "ERROR: Label was defined twice\n");
                HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR); // 52
            }

            $labelKeys[] = $labelKey;
            return [
                $labelKey => $i
            ];
        }, $labelInstructions, array_keys($labelInstructions));


        $this->labels = array_reduce($this->labels, function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);

        $i = 0;

        // Iterate over the array using foreach loop
        while ($i < count($instructions)) {
            $is_jump = false;
            
            $item = $instructions[$i];

            $opcode = $item['opcode'];
            $arguments = $item['arguments'];

            $value = null;

            switch (strtoupper($opcode)) {
                case "MOVE": {
                        [
                            "arg1" => [1 => $arg],
                            "arg2" => [0 => $type, 1 => $value]

                        ] = $arguments;

                        // Assign value to a variable
                        $variable = $this->stack->getVariable(new Variable($arg));


                        if ($type === 'var') {
                            $valueVariable = $this->stack->getVariable(new Variable($value));
                            $value = $valueVariable->getValue();
                        }


                        $variable->assign($type, $value);

                        break;
                    }
                case "CREATEFRAME": {
                        $this->stack->tframe = new Frame();
                        break;
                    }
                case "PUSHFRAME": {
                        if (!isset($this->stack->tframe)) {
                            fwrite(STDERR, "ERROR: frame was not created\n");
                            HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR); // 55
                        } else {
                            // TF -> LF
                            array_push($this->stack->lframe, $this->stack->tframe);
                            $this->stack->tframe = null;
                        }
                        break;
                    }
                case "POPFRAME": {
                        if (empty($this->stack->lframe)) {
                            fwrite(STDERR, "ERROR: stack is empty\n");
                            HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR); // 55
                        } else {
                            // LF -> TF
                            $this->stack->tframe = array_pop($this->stack->lframe);
                        }
                        break;
                    }
                case "DEFVAR": {
                        ["arg1" => [0 => $type, 1 => $arg]] = $arguments;
                        // Declare variables
                        $this->stack->declareVariable(new Variable($arg));

                        break;
                    }
                case "CALL": {
                        ["arg1" => [0 => $type, 1 => $label]] = $arguments;
                        
                        if ($type !== 'label') {
                            fwrite(STDERR, "ERROR: Invalid argument type for CALL instruction. Expected 'label'\n");
                            HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
                        }
                   
                        if (!isset($this->labels[$label])) {
                            fwrite(STDERR, "ERROR: Label not found\n");
                            HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR); // 52
                        }

                        // Save the current position to the call stack
                        array_push($this->callStack, $i);
                        // Jump to the label
                        // $this->instructionCounter = $this->labels[$label] + 1;
                        $i = $this->labels[$label];

                        break;
                    }
                case "RETURN": {
                        if (empty($this->callStack)) {
                            fwrite(STDERR, "ERROR: Call stack is empty\n");
                            HelperFunctions::validateErrorCode(ReturnCode::VALUE_ERROR); // 56
                        }

                        // Return to the last position
                        $i = array_pop($this->callStack);
                        break;
                    }


                case "PUSHS": {
                        ["arg1" => [0 => $type, 1 => $value]] = $arguments;

                        $symbol = $this->getSymbol($type, $value);

                        $symbValue = $symbol->getValue();
                        $this->dataStack[] = $symbValue;
                        break;
                    }
                case "POPS": {
                        ["arg1" => [0 => $type, 1 => $var]] = $arguments;

                        if (empty($this->dataStack)) {
                            fwrite(STDERR, "ERROR: Data stack is empty\n");
                            HelperFunctions::validateErrorCode(ReturnCode::VALUE_ERROR); // 56
                        }

                        $symbol = array_pop($this->dataStack);

                        // Store the popped value in the specified variable
                        $variable = $this->stack->getVariable(new Variable($var));
                        $variable->assign("string", $symbol);

                        break;
                    }


                case "ADD":
                case "SUB":
                case "MUL":
                case "IDIV": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        
                        if ($symbol1->getType() !== "int" || $symbol2->getType() !== "int") {
                            fwrite(STDERR, "ERROR: Invalid operand type\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        switch ($opcode) {
                            case "ADD":
                                $value = $symbol1->getValue() + $symbol2->getValue();
                                break;
                            case "SUB":
                                $value = $symbol1->getValue() - $symbol2->getValue();
                                break;
                            case "MUL":
                                $value = $symbol1->getValue() * $symbol2->getValue();
                                break;
                            case "IDIV":
                                if ($symbol2->getValue() == 0) {
                                    fwrite(STDERR, "ERROR: Division by zero\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_VALUE_ERROR); // 57
                                }
                                $value = $symbol1->getValue() / $symbol2->getValue();
                                break;
                        }

                        $variable->assign("int", $value);
                        break;
                    }

                case "LT":
                case "GT":
                case "EQ": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        if ($symbol1->getType() !== $symbol2->getType() && $symbol1->getType() !== 'nil' && $symbol2->getType() !== 'nil') {
                            fwrite(STDERR, "ERROR: Must have the same operand type\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        if (($symbol1->getType() === 'nil' || $symbol2->getType() === 'nil') && $opcode !== 'EQ') {
                            fwrite(STDERR, "ERROR: Use nil only with EQ instruction\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        switch ($opcode) {
                            case "LT":
                                $value = $symbol1->getValue() < $symbol2->getValue();
                                break;
                            case "GT":
                                $value = $symbol1->getValue() > $symbol2->getValue();
                                break;
                            case "EQ":
                                $value = $symbol1->getValue() == $symbol2->getValue();
                                break;
                        }
                        if ($value == 1) {
                            $value = "true";
                        } else if ($value == 0) {
                            $value = "false";
                        }
                        $variable->assign("bool", $value);
                        break;
                    }
                case "AND":
                case "OR": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        if ($symbol1->getType() !== "bool" || $symbol2->getType() !== "bool") {
                            fwrite(STDERR, "ERROR: Must have the same operand type\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }


                        switch ($opcode) {
                            case "AND":
                                if ($symbol1->getValue() === "false" || $symbol2->getValue() === "false") {
                                    $value = 0;
                                } else {
                                    $value = 1;
                                }
                                break;
                            case "OR":
                                if ($symbol1->getValue() === "true" || $symbol2->getValue() === "true") {
                                    $value = 1;
                                } else {
                                    $value = 0;
                                }
                                break;
                        }
                        if ($value == 1) {
                            $value = "true";
                        } else if ($value == 0) {
                            $value = "false";
                        }

                        $variable->assign("bool", $value);
                        break;
                    }
                case "NOT": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

                        if ($symbol->getType() !== "bool") {
                            fwrite(STDERR, "ERROR: Must be bool in the second argument\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        if ($symbol->getValue() === "true") {
                            $value = "false";
                        } else {
                            $value = "true";
                        }

                        $variable->assign("bool", $value);
                        break;
                    }
                case "INT2CHAR": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

                        if ($symbol->getType() !== "int") {

                            fwrite(STDERR, "ERROR: Must be int in the second argument\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        $variable = $this->stack->getVariable(new Variable($name));

                        $value = $symbol->getValue();

                        if ($value < 0 || $value > 0x10FFFF) {
                            fwrite(STDERR, "ERROR: Invalid unicode value\n");
                            HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // 58
                        }

                        $value = mb_chr($value);
                        $variable->assign("string", $value);
                        break;
                    }
                case "STRI2INT": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        
                        if ($symbol1->getType() !== "string" || $symbol2->getType() !== "int") {
                            fwrite(STDERR, "ERROR: Invalid argument type\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        $variable = $this->stack->getVariable(new Variable($name)); // for unicode value

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                        $value = $symbol1->getValue();
                        $index = $symbol2->getValue();

                        // Check if the character index($value1) and string length($value2) are non-negative 
                        // and if the index is within the range of the string length
                        if ($index < 0 || $index >= mb_strlen($value)) {
                            fwrite(STDERR, "ERROR: Invalid index or string length\n");
                            HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // 58
                        }
                        // to unicode value
                        $char = mb_substr($value, $index, 1);
                        $ordinalValue = mb_ord($char);
                        $variable->assign("int", (string)$ordinalValue);
                        break;
                    }

                case "READ": {
                        [
                            "arg1" => [0 => $type, 1 => $var],
                            "arg2" => [0 => $type, 1 => $typeValue]

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($var));

                        $input = $this->input->readString();
                        $variable->assign($typeValue, $input);

                        break;
                    }
                case "WRITE": {
                        [
                            "arg1" => [0 => $type, 1 => $value]

                        ] = $arguments;
                        
                        
                        $symbol = $this->getSymbol($type, $value);
                        
                        $value = $symbol->getValue();
                        $value = preg_replace('/\\\032/', ' ', $value);
                        $value = preg_replace('/\\\010/', "\n", $value);
                        
                        echo $value;

                        break;
                    }

                case "CONCAT":
                case "GETCHAR":
                case "SETCHAR": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;
                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                        switch ($opcode) {
                            case "CONCAT":
                                if ($symbol1->getType() !== "string" || $symbol2->getType() !== "string") {
                                    fwrite(STDERR, "ERROR: Invalid operand type in CONCAT\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                                }
                                $value = $symbol1->getValue() . $symbol2->getValue();
                                break;
                            case "GETCHAR":
                                if ($symbol1->getType() !== "string" || $symbol2->getType() !== "int") {

                                    fwrite(STDERR, "ERROR: Invalid operand type\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                                }
                                $string = $symbol1->getValue();
                                $index = intval($symbol2->getValue());

                                if ($index < 0 || $index >= mb_strlen($string)) {
                                    fwrite(STDERR, "ERROR: Invalid index or string length\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // Error code 58
                                }
                                $value = mb_substr($string, $index, 1);
                                break;
                            case "SETCHAR":

                                if ($symbol1->getType() !== "int" || $symbol2->getType() !== "string") {
                                    fwrite(STDERR, "ERROR: Invalid operand type \n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                                }

                                $index = $symbol1->getValue();
                                $newValue = $symbol2->getValue();


                                if ($index < 0 || $index >= mb_strlen($variable->getValue()) || mb_strlen($newValue) == 0) {
                                    fwrite(STDERR, "ERROR: Invalid index or string length\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // 58
                                }

                                $value = $variable->getValue();
                                $value = mb_substr($value, 0, $index) . $newValue . mb_substr($value, $index + 1);
                                break;
                        }

                        $variable->assign("string", $value);
                        break;
                    }
                case "STRLEN": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);
                        $typeSymb = $symbol->getType();

                        if ($typeSymb !== "string") {
                            fwrite(STDERR, "ERROR: Invalid operand type. Must be 'string'\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        $variable = $this->stack->getVariable(new Variable($name));


                        $length = mb_strlen($valueSymb);

                        $variable->assign("int", $length);
                    }

                case "TYPE": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;




                        // Dynamically determine the type of the symbol
                        if ($typeSymb === "var") {
                            $symbol = $this->getSymbol($typeSymb, $valueSymb);
                            $type = $symbol->getType();
                        } else {

                            // For constants, the type is the same as the type specifier
                            $type = $typeSymb;
                        }

                        if (empty($type)) {
                            // Handle the case where the type could not be determined
                            $type = "";
                        }

                        // Store the type in the specified variable

                        $variable = $this->stack->getVariable(new Variable($name));
                        $variable->assign("string", $type);
                        break;
                    }



                case "LABEL": {
                        [
                            "arg1" => [0 => $typeLabel, 1 => $nameLabel],
                        ] = $arguments;


                        // if (isset($this->labels[$nameLabel])) {
                        //     fwrite(STDERR, "ERROR: Label already exists\n");
                        //     HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR); // 52
                        // }
                        // $this->labels[$nameLabel] = $this->instructionCounter; // current instruction counter
                        // break;
                        break;
                    }


                case "JUMP": {
                        [
                            "arg1" => [0 => $var, 1 => $labelKey]

                        ] = $arguments;

                        if (!key_exists($labelKey, $this->labels)) {
                            fwrite(STDERR, "ERROR: Label $labelKey is not defined\n");
                            HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR); // 53
                            break;
                        }

                        $jumpToIdx = $this->labels[$labelKey];
                        $is_jump = true;

                        $i = $jumpToIdx;

                        // print_r($this->labels);
                        // print("$jumpToIdx\n");

                        break;
                    }

                case "JUMPIFEQ":
                case "JUMPIFNEQ": {
                        [
                            "arg1" => [0 => $var, 1 => $labelKey],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        if (!key_exists($labelKey, $this->labels)) {
                            fwrite(STDERR, "ERROR: Label $labelKey is not defined\n");
                            HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR); // 53
                            break;
                        }

                        $jumpToIdx = $this->labels[$labelKey];

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                        if ($symbol1->getType() !== $symbol2->getType() && $symbol1->getType() !== 'nil' && $symbol2->getType() !== 'nil') {
                            fwrite(STDERR, "ERROR: Must have the same operand type\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                            break;
                        }

                        switch ($opcode) {
                            case "JUMPIFEQ":
                                if ($symbol1->getValue() == $symbol2->getValue()) {
                                    $i = $jumpToIdx;
                                    $is_jump = true;
                                }
                                break;
                            case "JUMPIFNEQ":

                                if ($symbol1->getValue() != $symbol2->getValue()) {
                                    $i = $jumpToIdx;
                                    $is_jump = true;
                                }
                                break;
                        }
                        break;
                    }


                case "EXIT":
                case "DPRINT": {
                        [
                            "arg1" => [0 => $typeSymb, 1 => $valueSymb],
                        ] = $arguments;

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);
                        $value = $symbol->getValue();
                        $type = $symbol->getType();

                        switch ($opcode) {
                            case "EXIT":
                                if($type !== "int"){
                                    fwrite(STDERR, "ERROR: Invalid operand value in EXIT\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 57
                                }
                                if (!is_int($value) || $value < 0 || $value > 9) {
                                    fwrite(STDERR, "ERROR: Invalid operand value in EXIT\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_VALUE_ERROR); // 57
                                }
                                return $value;
                            case "DPRINT":
                                fwrite(STDERR, $value . PHP_EOL);
                                break;
                        }
                    }
                case "BREAK": {
                        //TODO 
                        // $state = [
                        //     'position' => $this->$item,
                        //     'instructionsExecuted' => $this->instructionCounter,
                        // ];
                        break;
                    }
                default: {
                    fwrite(STDERR, "Opcode $opcode is unrecognized\n");
                    HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
                }
            }

            if(!$is_jump){
                $i++;
            }

            // print_r($this->stack);
            // print_r($this->labels);
        }

        return 0;
    }
}
