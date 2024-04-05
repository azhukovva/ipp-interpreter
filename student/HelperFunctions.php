<?php

namespace IPP\Student;

use IPP\Core\ReturnCode;

class HelperFunctions
{
    public static function validateErrorCode(int $errorCode): void
    {
        switch ($errorCode) {
            case ReturnCode::INVALID_SOURCE_STRUCTURE:
            case ReturnCode::INVALID_XML_ERROR:
            case ReturnCode::SEMANTIC_ERROR:
            case ReturnCode::OPERAND_TYPE_ERROR:
            case ReturnCode::VARIABLE_ACCESS_ERROR:
            case ReturnCode::FRAME_ACCESS_ERROR:
            case ReturnCode::VALUE_ERROR:
            case ReturnCode::OPERAND_VALUE_ERROR:
            case ReturnCode::STRING_OPERATION_ERROR:
            case ReturnCode::INTEGRATION_ERROR:
            case ReturnCode::INTERNAL_ERROR:
                exit($errorCode);
            default:
                exit(ReturnCode::INTERNAL_ERROR);
        }
    }

    public static function validateXML(\DOMDocument $dom): void
    {
        $xmlRoot = $dom->documentElement;
        $language = $xmlRoot->getAttribute('language');

        if ($xmlRoot === null || $xmlRoot->nodeName !== 'program' || strtolower($language) !== 'ippcode24') {
            fwrite(STDERR, "ERROR: Invalid XML structure\n");
            self::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
        }

        foreach ($xmlRoot->attributes as $attribute) {
            $attribName = $attribute->nodeName;
            $allowedAttributes = ['language', 'name', 'description'];

            if (!in_array($attribName, $allowedAttributes)) {
                fwrite(STDERR, "ERROR: Program element can only contain 'language', 'name', or 'description' attributes\n");
                self::validateErrorCode(ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
        }
    }


    
}
