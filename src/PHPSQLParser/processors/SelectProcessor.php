<?php


namespace PHPSQLParser\processors;
require_once(dirname(__FILE__) . '/SelectExpressionProcessor.php');

class SelectProcessor extends SelectExpressionProcessor {

    public function process($tokens) {
        $expression = "";
        $expressionList = array();
        foreach ($tokens as $token) {
            if ($this->isCommaToken($token)) {
                $expression = parent::process(trim($expression));
                $expression['delim'] = ',';
                $expressionList[] = $expression;
                $expression = "";
            } else {
                switch (strtoupper($token)) {

                case 'DISTINCT':
                    $expression = parent::process(trim($token));
                    $expression['delim'] = ' ';
                    $expressionList[] = $expression;
                    $expression = "";
                    break;

                default:
                    $expression .= $token;
                }
            }
        }
        if ($expression) {
            $expression = parent::process(trim($expression));
            $expression['delim'] = false;
            $expressionList[] = $expression;
        }
        return $expressionList;
    }
}
?>
