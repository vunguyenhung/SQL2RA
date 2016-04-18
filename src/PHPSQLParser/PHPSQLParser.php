<?php

namespace PHPSQLParser;
use PHPSQLParser\positions\PositionCalculator;
use PHPSQLParser\processors\DefaultProcessor;
use PHPSQLParser\utils\PHPSQLParserConstants;

require_once dirname(__FILE__) . '/positions/PositionCalculator.php';
require_once dirname(__FILE__) . '/processors/DefaultProcessor.php';
require_once dirname(__FILE__) . '/utils/PHPSQLParserConstants.php';

class PHPSQLParser {

    public $parsed;

    /**
     * Constructor. It simply calls the parse() function.
     * Use the public variable $parsed to get the output.
     *
     * @param String  $sql           The SQL statement.
     * @param boolean $calcPositions True, if the output should contain [position], false otherwise.
     */
    public function __construct($sql = false, $calcPositions = false) {
        if ($sql) {
            $this->parse($sql, $calcPositions);
        }
    }

    /**
     * It parses the given SQL statement and generates a detailled
     * output array for every part of the statement. The method can
     * also generate [position] fields within the output, which hold
     * the character position for every statement part. The calculation
     * of the positions needs some time, if you don't need positions in
     * your application, set the parameter to false.
     *
     * @param String  $sql           The SQL statement.
     * @param boolean $calcPositions True, if the output should contain [position], false otherwise.
     *
     * @return array An associative array with all meta information about the SQL statement.
     */
    public function parse($sql, $calcPositions = false) {

        $processor = new DefaultProcessor();
        $queries = $processor->process($sql);

        // calc the positions of some important tokens
        if ($calcPositions) {
            $calculator = new PositionCalculator();
            $queries = $calculator->setPositionsWithinSQL($sql, $queries);
        }

        // store the parsed queries
        $this->parsed = $queries;
        return $this->parsed;
    }

    /**
     * Add a custom function to the parser.  no return value
     *
     * @param String $token The name of the function to add
     *
     * @return null
     */
    public function addCustomFunction($token) {
        PHPSQLParserConstants::getInstance()->addCustomFunction($token);
    }

    /**
     * Remove a custom function from the parser.  no return value
     *
     * @param String $token The name of the function to remove
     *
     * @return null
     */
    public function removeCustomFunction($token) {
        PHPSQLParserConstants::getInstance()->removeCustomFunction($token);
    }

    /**
     * Returns the list of custom functions
     *
     * @return array Returns an array of all custom functions
     */
    public function getCustomFunctions() {
        return PHPSQLParserConstants::getInstance()->getCustomFunctions();
    }
}
?>
