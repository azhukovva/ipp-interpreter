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
    private array $dataStack;
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
        $this->dataStack = array();
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
                        if ($symbol1->getType() !== $symbol2->getType()) {
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

                        if ($typeSymb !== "int") {
                            fwrite(STDERR, "ERROR: Must be int in the second argument\n");
                            HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                        }

                        $variable = $this->stack->getVariable(new Variable($name));

                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

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


                        if ($typeSymb1 !== "string" || $typeSymb2 !== "int") {
                            $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                            if (is_int($symbol2->getValue())) {
                            } else {
                                fwrite(STDERR, "ERROR: Invalid argument type\n");
                                HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                            }
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

                        echo $symbol->getValue();

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
                                //TODO - to chekType function
                                if ($symbol1->getType() !== "int" || $symbol2->getType() !== "string") {
                                    fwrite(STDERR, "ERROR: Invalid operand type\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::OPERAND_TYPE_ERROR); // 53
                                }
                                $string = $symbol1->getValue();
                                $index = intval($symbol2->getValue());

                                if ($index < 0 || $index >= mb_strlen($string)) {
                                    fwrite(STDERR, "ERROR: Invalid index or string length\n");
                                    HelperFunctions::validateErrorCode(ReturnCode::STRING_OPERATION_ERROR); // Error code 58
                                }
                                $char = mb_substr($string, $index, 1);
                                break;
                            case "SETCHAR":

                                if ($symbol1->getType() !== "int" || $symbol2->getType() !== "string") {
                                    fwrite(STDERR, "ERROR: Invalid operand type\n");
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
                        $variable = $this->stack->getVariable(new Variable($name));
                        $symbol = $this->getSymbol($typeSymb, $valueSymb);

                        $value = $symbol->getValue();
                        $length = mb_strlen($value);


                        $this->stack->getVariable(new Variable($name));
                        //TODO 
                        // if ($type === 'var') {
                        //     $valueVariable = $this->stack->getVariable(new Variable($value));
                        //     $value = $valueVariable->getValue();
                        // }
                        $variable->assign("int", $length);
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
        }

        return 0;
    }
}
