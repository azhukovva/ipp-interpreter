<?php

namespace IPP\Student;

require_once 'Literals.php';

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Student\Literals\Symbol;
use IPP\Student\Literals\Variable;

echo "Hello World!\n";

class Interpreter extends AbstractInterpreter
{
    // Stack with frames
    private Stack $stack;

    private function getSymbol(string $type, string $value): Symbol {
        if ($type == "var") {
            return $this->stack->getVariable(new Variable($value));
        }
        
        return new Symbol($type, $value);
    }


    public function execute(): int
    {
        $this->stack = new Stack;
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
        // InstructionValidator::validate($instructions);

        $labelInstructions = array_filter($instructions, function($item) { return $item["opcode"] == "LABEL"; });
        
        $labels = array_map(function ($item) { 

            $order = $item['order'];
            $arguments = $item['arguments'];

            [ "arg1" => [ 0 => $type, 1 => $labelKey ] ] = $arguments;
            
            return [ 
                $labelKey => $order
            ];
        
        }, $labelInstructions);

        $labels = array_reduce($labels, function($carry, $item) {
            return array_merge($carry, $item);
        }, []);

        // Iterate over the array using foreach loop
        for ($i = 0; $i < count($instructions); $i++) {
            $item = $instructions[$i];

            $opcode = $item['opcode'];
            $order = $item['order'];
            $arguments = $item['arguments'];

            switch($opcode){
                case "DEFVAR": {
                    [ "arg1" => [ 0 => $type, 1 => $arg ] ] = $arguments;

                    // Declare variables
                    $this->stack->declareVariable(new Variable($arg));
                    break;
                }
                case "MOVE": {
                    [ 
                        "arg1" => [ 1 => $arg ],
                        "arg2" => [ 0 => $type, 1 => $value]
                    
                    ] = $arguments;

                    // Assign value to a variable
                    $variable = $this->stack->getVariable(new Variable($arg));
                    $variable->assign($type, $value);
                    break;
                }
                case "MUL": {
                    [ 
                        "arg1" => [ 0 => $var, 1 => $name ],
                        "arg2" => [ 0 => $typeSymb1, 1 => $valueSymb1 ],
                        "arg3" => [ 0 => $typeSymb2, 1 => $valueSymb2 ],
                    
                    ] = $arguments;

                    $variable = $this->stack->getVariable(new Variable($name));

                    $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                    $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                    $value = $symbol1->getValue() * $symbol2->getValue();
                    $variable->assign($variable->getType(), $value);
                    break;
                }
                case "ADD": {
                    [ 
                        "arg1" => [ 0 => $var, 1 => $name ],
                        "arg2" => [ 0 => $typeSymb1, 1 => $valueSymb1 ],
                        "arg3" => [ 0 => $typeSymb2, 1 => $valueSymb2 ],
                    
                    ] = $arguments;

                    $variable = $this->stack->getVariable(new Variable($name));

                    $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                    $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);
                    $value = $symbol1->getValue() + $symbol2->getValue();
                    $variable->assign($variable->getType(), $value);
                    break;
                }
                case "JUMPIFEQ": {
                    [ 
                        "arg1" => [ 0 => $var, 1 => $labelKey ],
                        "arg2" => [ 0 => $typeSymb1, 1 => $valueSymb1 ],
                        "arg3" => [ 0 => $typeSymb2, 1 => $valueSymb2 ],
                    
                    ] = $arguments;

                    $jumpToIdx = $labels[$labelKey];

                    $symbol1 = $this->getSymbol($typeSymb1, $valueSymb1);
                    $symbol2 = $this->getSymbol($typeSymb2, $valueSymb2);

                    if($symbol1->getValue() == $symbol2->getValue()){
                        $i = $jumpToIdx;
                    }
                    break;
                }
                case "JUMP": {
                    [ 
                        "arg1" => [ 0 => $var, 1 => $labelKey ]
                    
                    ] = $arguments;

                    $jumpToIdx = $labels[$labelKey] - 1;

                    $i = $jumpToIdx;

                    break;
                }
                case "WRITE": {
                    [ 
                        "arg1" => [ 0 => $type, 1 => $value ]
                    
                    ] = $arguments;

                    $symbol = $this->getSymbol($type, $value);

                    echo $symbol->getValue()."\n";

                    break;
                }
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
