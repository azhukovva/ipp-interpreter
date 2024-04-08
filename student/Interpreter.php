<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

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
        InstructionValidator::validate($instructions);

        return 0;
    }
}
