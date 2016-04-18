<?php

namespace PHPSQLParser\processors;
use PHPSQLParser\utils\ExpressionType;

require_once dirname(__FILE__) . '/../utils/ExpressionType.php';
require_once dirname(__FILE__) . '/DefaultProcessor.php';
require_once dirname(__FILE__) . '/AbstractProcessor.php';

class BracketProcessor extends AbstractProcessor {

    protected function processTopLevel($sql) {
        $processor = new DefaultProcessor();
        return $processor->process($sql);
    }

    public function process($tokens) {

        $token = $this->removeParenthesisFromStart($tokens[0]);
        $subtree = $this->processTopLevel($token);

        if (isset($subtree['BRACKET'])) {
            $subtree = $subtree['BRACKET'];
        }

        if (isset($subtree['SELECT'])) {
            $subtree = array(
                    array('expr_type' => ExpressionType::QUERY, 'base_expr' => $token, 'sub_tree' => $subtree));
        }

        return array(
                array('expr_type' => ExpressionType::BRACKET_EXPRESSION, 'base_expr' => trim($tokens[0]),
                        'sub_tree' => $subtree));
    }

}

?>
