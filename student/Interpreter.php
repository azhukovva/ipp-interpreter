<?php

namespace IPP\Student;

require_once 'Literals.php';

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Student\Literals\Variable;

echo "Hello World!\n";

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
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
        // INSTRUCTIONS's array
        $instructions = ParserXML::parseXML($dom); 
        // InstructionValidator::validate($instructions);

        // Stack with frames
        $stack = new Stack;

        // Iterate over the array using foreach loop
        foreach ($instructions as $item) {

            $opcode = $item['opcode'];
            $order = $item['order'];
            $arguments = $item['arguments'];

            switch($opcode){
                case "DEFVAR": {
                    [ "arg1" => [ 0 => $type, 1 => $arg ] ] = $arguments;

                    // Declare variables
                    $stack->declareVariable(new Variable($arg));
                    break;
                }
                case "MOVE": {
                    [ 
                        "arg1" => [ 1 => $arg ],
                        "arg2" => [ 0 => $type, 1 => $value]
                    
                    ] = $arguments;

                    // Assign value to a variable
                    $variable = $stack->getVariable(new Variable($arg));
                    $variable->assign($type, $value);
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

        print_r($stack);

        return 0;
    }
}
