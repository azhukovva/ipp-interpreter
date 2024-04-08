<?php

namespace IPP\Student;

use IPP\Student\HelperFunctions;
use IPP\Core\ReturnCode;

class ParserXML
{
    // Returns an array of instructions parsed from the XML document
    public static function parseXML(\DOMDocument $document): array
    {
        $rootXML = $document->documentElement;
        HelperFunctions::validateXML($document, $rootXML);
     
        foreach ($rootXML->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement) {
                fwrite(STDERR, "ERROR: Sub-elements of 'program' must be 'instruction'\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            }

            if ($childNode->nodeName !== 'instruction') {
                continue;
            }

            $opcode = $childNode->getAttribute('opcode');
            $order = $childNode->getAttribute('order');
            if ($opcode === '' || $order === '') {
                fwrite(STDERR, "ERROR: Missing attribute in 'instruction' element\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            }

            $arguments = self::parseArguments($childNode);
            print_r($arguments);

            // EVERYTHING IS OK!
            $instruction = [
                'opcode' => $opcode,
                'order' => $order,
                'arguments' => $arguments
            ];

            $instructions[] = $instruction; // current instruction to parsed instructions's array
            
        }
        $array = [];
        return $array;
    }

    private static function validateArgumentType(string $argName, string $type, int $argOrder): void
    {
        $argsType = ['int', 'bool', 'string', 'nil', 'label', 'type', 'var']; // allowed types of arguments

        //TODO - check if the argument order is correct ~ expected 1/2/3 (?)
        if (($argName !== 'arg' . $argOrder) || $argOrder > 3 || !in_array($type, $argsType)) {
            fwrite(STDERR, "ERROR: Invalid argument order\n");
            HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
    }

    private static function isValidArgument(\DOMNode $node): bool
    {
        // In an XML document, 'text' nodes are usually the ones containing whitespace between elements, 
        // which are not relevant for the parsing process.
        // Skip these nodes because they don't represent argument elements
        return $node instanceof \DOMElement && $node->nodeName !== 'text' && $node->nodeName !== '#text';
    }

    private static function parseArguments(\DOMElement $instruction): array
    {
        $arguments = [];
        $argOrder = 1;

        foreach ($instruction->childNodes as $childNode) {
            if (self::isValidArgument($childNode)) {
                // Fullfill the argument array with the name, type and value of the argument
                $argName = $childNode->nodeName;
                $argType = $childNode->getAttribute('type'); // value of the attribute named 'type' from the current XML node
                $argValue = trim($childNode->nodeValue);
                $argNumber = substr($argName, 3); // get the number of the argument from its name, arg1/2/3

                self::validateArgumentType($argName, $argType, $argOrder);

                $arguments[$argName] = [$argType, $argValue];
                $argOrder++;
            }
        }
        return $arguments;
    }

    //REVIEW - do I need this function??
    //     private static function validateOrderNumbers(array $instructions): void
    // {
    //     $encounteredOrders = [];
    //     foreach ($instructions as $instruction) {
    //         $order = $instruction['order'];
    //         if ($order < 0) {
    //             fwrite(STDERR, "Error: Order number '$order' cannot be negative\n");
    //             HelperFunctions::validateErrorCode(ReturnCode::INVALID_XML_ERROR);
    //         }
    //         if (in_array($order, $encounteredOrders)) {
    //             fwrite(STDERR, "Error: Duplicate order number '$order' detected\n");
    //             HelperFunctions::validateErrorCode(ReturnCode::INVALID_XML_ERROR);
    //         } else {
    //             $encounteredOrders[] = $order;
    //         }
    //     }
    // }
}
