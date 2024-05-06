<?php

namespace IPP\Student;

use IPP\Student\HelperFunctions;
use IPP\Core\ReturnCode;

class ParserXML
{
    /**
     * Parse XML document and return an array of instructions.
     *
     * @param \DOMDocument $document The XML document to parse.
     * @return array<int<0, max>, array<string, array<string, array<int, string>>|string>> An array of instructions.
     */
    public static function parseXML(\DOMDocument $document): array
    {
        $rootXML = $document->documentElement;

        HelperFunctions::validateXML($document, $rootXML);

        $orders = [];
        $instructions = [];


        foreach ($rootXML->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement) {
                fwrite(STDERR, "ERROR: Sub-elements of 'program' must be 'instruction'\n");
                HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            } else {
                if ($childNode->nodeName !== 'instruction') {
                    fwrite(STDERR, "ERROR: Sub-elements of 'program' must be 'instruction'\n");
                    HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
                }

                $opcode = $childNode->getAttribute('opcode');
                $order = $childNode->getAttribute('order');

                if ($opcode === '' || $order === '' || !is_numeric($order) || $order < 0) {
                    fwrite(STDERR, "ERROR: Invalid 'instruction' structure\n");
                    HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
                }

                if (in_array($order, $orders)) {
                    fprintf(STDERR, "ERROR: Dupcicit order $order\n");
                    HelperFunctions::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE); // 32
                }

                $orders[] = $order;
                $arguments = self::parseArguments($childNode);

                // EVERYTHING IS OK!
                $instruction = [
                    'opcode' => $opcode,
                    'order' => $order,
                    'arguments' => $arguments
                ];

                $instructions[] = $instruction; // current instruction to parsed instructions's array

            }
        }
        return $instructions;
    }

    /**
     * Validate the type of an argument.
     *
     * @param string $argName The name of the argument.
     * @param string $type The type of the argument.
     * @param int $argOrder The order of the argument.
     */
    private static function validateArgumentType(string $argName, string $type, int $argOrder): void
    {
        $argsType = ['int', 'bool', 'string', 'nil', 'label', 'type', 'var']; // allowed types of arguments

        //TODO - check if the argument order is correct ~ expected 1/2/3 (?)
        if (($argName !== 'arg' . $argOrder) || $argOrder > 3 || $argOrder < 0 || !in_array($type, $argsType)) {
            fwrite(STDERR, "ERROR: Invalid source structure\n");
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

    /**
     * Parse arguments and return an array of arguments.
     *
     * @param \DOMElement $instruction The instruction element to parse.
     * @return array<string, array<int, string>> An array of arguments.
     */
    private static function parseArguments(\DOMElement $instruction): array
    {
        $arguments = [];
        $argOrder = 1;

        $rawArguments = iterator_to_array($instruction->childNodes);

        usort($rawArguments, function ($a, $b) {
            if ($a instanceof \DOMElement && $b instanceof \DOMElement) {
                return strcmp($a->tagName, $b->tagName);
            }
            return 0;
        });

        foreach ($rawArguments as $childNode) {
            // Ensure $childNode is a DOMElement before accessing its properties
            if ($childNode instanceof \DOMElement && self::isValidArgument($childNode)) {
                // Fulfill the argument array with the name, type, and value of the argument
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
}
