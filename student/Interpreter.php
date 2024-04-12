<?php

namespace IPP\Student;

require_once 'Literals.php';

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Student\Literals\Symbol;
use IPP\Student\Literals\Variable;
use IPP\Core\ReturnCode;


class Interpreter extends AbstractInterpreter
{
    // Stack with frames
    private Stack $stack;

    private array $callStack;
    private array $labels;
    private int $instructionCounter = 0;

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
        // TODO: Start your code here

        // Check \IPP\Core\AbstractInterpreter for predefined I/O objects:

        $dom = $this->source->getDOMDocument();

        /**
         * preserveWhiteSpace is set to true by default. This means that any spaces, tabs, newlines, or other whitespace characters 
         * found within elements and between elements will be preserved as part of the text nodes
         * 
         * If set to false, the parser does not preserve white space, It trims leading and trailing white space 
         * from text nodes
         * 
         * <3
         */
        $dom->preserveWhiteSpace = false;

        /**
         * After preserveWhiteSpace was set to false, we need to rebuild  xml again
         */
        $dom->loadXml($dom->saveXML());

        // $val = $this->input->readString();
        // $this->stdout->writeString("stdout");
        // $this->stderr->writeString("stderr");

        // handle options input
        // parse the XML document

        // TODO instructions must be sorted!

        // INSTRUCTIONS's array
        $instructions = ParserXML::parseXML($dom);
        InstructionValidator::validate($instructions);

        // Get all LABEL instructions
        $labelInstructions = array_filter($instructions, function ($item) {
            return $item["opcode"] == "LABEL";
        });

        $labels = array_map(function ($item) {

            $order = $item['order'];
            $arguments = $item['arguments'];

            ["arg1" => [0 => $type, 1 => $labelKey]] = $arguments;

            return [
                $labelKey => $order
            ];
        }, $labelInstructions);

        $labels = array_reduce($labels, function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);

        // Iterate over the array using foreach loop
        for ($i = 0; $i < count($instructions); $i++) {
            $item = $instructions[$i];

            $opcode = $item['opcode'];
            $order = $item['order'];
            $arguments = $item['arguments'];

            switch ($opcode) {
                case "MOVE": {
                        [
                            "arg1" => [1 => $arg],
                            "arg2" => [0 => $type, 1 => $value]

                        ] = $arguments;

                        // Assign value to a variable
                        $variable = $this->stack->getVariable(new Variable($arg));
                        $variable->assign($type, $value);
                        break;
                    }
                case "CREATEFRAME": {
                        $this->stack->tframe = new Frame();
                        break;
                    }
                case "PUSHFRAME": {
                        if (!isset($this->stack->tframe)) {
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
                            throw new \Exception("Error: Invalid argument type for CALL instruction. Expected 'label'");
                        }

                        // Save the current position to the call stack
                        $this->callStack[] = $this->instructionCounter + 1;
                        // Jump to the label
                        $this->instructionCounter = $this->labels[$label];
                        break;
                    }
                case "RETURN": {
                        if (empty($this->callStack)) {
                            throw new \Exception("Error: Call stack is empty");
                        }

                        // Return to the last position
                        $this->instructionCounter = array_pop($this->callStack);
                        break;
                    }


                case "PUSHS": {
                        ["arg1" => [0 => $type, 1 => $value]] = $arguments;

                        $symbol = $this->getSymbol($type, $value);
                        if ($this->stack->tframe !== null) {
                            // ⟨symb⟩ value TO the data stack
                            $this->stack->tframe->push($symbol);
                        } else {
                            HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR); // 55
                        }
                        break;
                    }
                case "POPS": {
                        ["arg1" => [0 => $type, 1 => $var]] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($var));
                        if ($this->stack->tframe !== null) {
                            // ⟨var⟩ value FROM the data stack
                            $variable->assign($type, $this->stack->tframe->pop()->getValue()); // removes the last element from the stack and assigns it to the $variable
                        } else {
                            HelperFunctions::validateErrorCode(ReturnCode::FRAME_ACCESS_ERROR); // 55
                        }
                        break;
                    }


                case "ADD": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        $value = $symbol1->getValue() + $symbol2->getValue();
                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "SUB": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        $value = $symbol1->getValue() - $symbol2->getValue();
                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "MUL": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        $value = $symbol1->getValue() * $symbol2->getValue();
                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "IDIV": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                        if ($symbol2->getValue() == 0) {
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_VALUE_ERROR); // 57
                        }
                        $value = $symbol1->getValue() / $symbol2->getValue();
                        $variable->assign($variable->getType(), $value);
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

                        if (($typeSymb1 === 'nil' || $typeSymb2 === 'nil') && $opcode !== 'EQ') {
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

                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "AND":
                case "OR":
                case "NOT": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                        switch ($opcode) {
                            case "AND":
                                $value = $symbol1->getValue() && $symbol2->getValue();
                                break;
                            case "OR":
                                $value = $symbol1->getValue() || $symbol2->getValue();
                                break;
                            case "NOT":
                                $value = !$symbol1->getValue();
                                break;
                        }

                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "INT2CHAR": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

                        $value = $symbol->getValue();

                        if ($value < 0 || $value > 0x10FFFF) {
                            HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // 58
                        }

                        $value = mb_chr($value);
                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "STRI2INT": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name)); // for unicode value

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                        $value1 = $symbol1->getValue();
                        $value2 = $symbol2->getValue();
                        // Check if the character index($value1) and string length($value2) are non-negative 
                        // and if the index is within the range of the string length
                        if ($value1 < 0 || $value2 < 0 || $value1 >= mb_strlen($value2)) {
                            HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // 58
                        }

                        // to unicode value
                        $value = mb_ord($value2[$value1]);
                        $variable->assign($variable->getType(), $value);
                        break;
                    }

                case "READ": {
                        [
                            "arg1" => [0 => $type, 1 => $var]

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($var));

                        $input = $this->input->readString();
                        $variable->assign($type, $input);

                        break;
                    }
                case "WRITE": {
                        [
                            "arg1" => [0 => $type, 1 => $value]

                        ] = $arguments;

                        $value = str_replace('\032', ' ', $value);
                        $value = str_replace('\010', "\n", $value); 

                        $symbol = $this->getSymbol($type, $value);

                        echo $symbol->getValue() . "\n";
                    
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
                                $value = $symbol1->getValue() . $symbol2->getValue();
                                break;
                            case "GETCHAR":
                                $value = $symbol1->getValue()[$symbol2->getValue()];
                                break;
                            case "SETCHAR":
                                $index = $symbol1->getValue();
                                $newValue = $symbol2->getValue();

                                if ($index < 0 || $index >= mb_strlen($variable->getValue()) || mb_strlen($newValue) == 0) {
                                    HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // 58
                                }

                                $value = $variable->getValue();
                                $newValue = mb_substr($value, 0, $index) . $newValue . mb_substr($value, $index + 1);
                                break;
                        }

                        $variable->assign($variable->getType(), $value);
                        break;
                    }
                case "STRLEN": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;

                        $variable = $this->stack->getVariable(new Variable($name));
                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

                        $value = $symbol->getValue();
                        $length = mb_strlen($value);

                        $this->stack->declareVariable(new Variable($var));
                        $this->stack->getVariable(new Variable($var))->assign("int", $length);
                    }


                case "TYPE": {
                        [
                            "arg1" => [0 => $var, 1 => $name],
                            "arg2" => [0 => $typeSymb, 1 => $valueSymb],

                        ] = $arguments;

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

                        if ($symbol === null) {
                            $type = "";
                        } else {
                            $type = $symbol->getType();
                        }

                        $this->stack->declareVariable(new Variable($var));
                        $this->stack->getVariable(new Variable($var))->assign("string", $type);
                    }



                case "LABEL": {
                        [
                            "arg1" => [0 => $typeLabel, 1 => $nameLabel],
                        ] = $arguments;

                        if (isset($this->labels[$nameLabel])) {
                            HelperFunctions::validateErrorCode(ReturnCode::SEMANTIC_ERROR); // 52
                        }

                        $this->labels[$nameLabel] = $this->instructionCounter; // current instruction counter
                        break;
                    }


                case "JUMP": {
                        [
                            "arg1" => [0 => $var, 1 => $labelKey]

                        ] = $arguments;

                        $jumpToIdx = $labels[$labelKey] - 1;

                        $i = $jumpToIdx;

                        break;
                    }

                case "JUMPIFEQ":
                case "JUMPIFNEQ": {
                        [
                            "arg1" => [0 => $var, 1 => $labelKey],
                            "arg2" => [0 => $typeSymb1, 1 => $valueSymb1],
                            "arg3" => [0 => $typeSymb2, 1 => $valueSymb2],

                        ] = $arguments;

                        $jumpToIdx = $labels[$labelKey];

                        $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                        $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                        switch ($opcode) {
                            case "JUMPIFEQ":
                                if ($symbol1->getValue() == $symbol2->getValue()) {
                                    $i = $jumpToIdx;
                                }
                                break;
                            case "JUMPIFNEQ":
                                if ($symbol1->getValue() != $symbol2->getValue()) {
                                    $i = $jumpToIdx;
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

                        if (!is_int($value) || $value < 0 || $value > 9) {
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_VALUE_ERROR); // 57
                        }

                        switch ($opcode) {
                            case "EXIT":
                                return $value;
                                break;
                            case "DPRINT":
                                fwrite(STDERR, $value . PHP_EOL);
                                break;
                        }
                    }

                case "BREAK": {
                        //TODO 
                        $state = [
                            'position' => $this->$item,
                            'instructionsExecuted' => $this->instructionCounter,
                        ];
                    }
                    // Access each item inside the loop
                    // echo "Opcode: " . $item['opcode'] . "\n";
                    // echo "Order: " . $item['order'] . "\n";

                    // // Access arguments array
                    // foreach ($item['arguments'] as $argName => $argValue) {
                    //     echo "Argument $argName: " . $argValue[0] . " - " . $argValue[1] . "\n";
                    // }

                    // Add more processing as needed...
            }

            // print_r($this->stack);
            // print_r($labels);

            return 0;
        }
    }
}
