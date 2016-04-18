<?php

namespace PHPSQLParser\processors;
use PHPSQLParser\utils\ExpressionType;

require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/SelectExpressionProcessor.php';
require_once dirname(__FILE__) . '/../utils/ExpressionType.php';

class OrderByProcessor extends AbstractProcessor {

    protected function processSelectExpression($unparsed) {
        $processor = new SelectExpressionProcessor();
        return $processor->process($unparsed);
    }

    protected function initParseInfo() {
        return array('base_expr' => "", 'dir' => "ASC", 'expr_type' => ExpressionType::EXPRESSION);
    }

    protected function processOrderExpression(&$parseInfo, $select) {
        $parseInfo['base_expr'] = trim($parseInfo['base_expr']);

        if ($parseInfo['base_expr'] === "") {
            return false;
        }

        if (is_numeric($parseInfo['base_expr'])) {
            $parseInfo['expr_type'] = ExpressionType::POSITION;
        } else {
            $parseInfo['no_quotes'] = $this->revokeQuotation($parseInfo['base_expr']);
            // search to see if the expression matches an alias
            foreach ($select as $clause) {
                if (empty($clause['alias'])) {
                    continue;
                }

                if ($clause['alias']['no_quotes'] === $parseInfo['no_quotes']) {
                    $parseInfo['expr_type'] = ExpressionType::ALIAS;
                    break;
                }
            }
        }

        if ($parseInfo['expr_type'] === ExpressionType::EXPRESSION) {
            $expr = $this->processSelectExpression($parseInfo['base_expr']);
            $expr['direction'] = $parseInfo['dir'];
            unset($expr['alias']);
            return $expr;
        }

        $result = array();
        $result['expr_type'] = $parseInfo['expr_type'];
        $result['base_expr'] = $parseInfo['base_expr'];
        if (isset($parseInfo['no_quotes'])) {
            $result['no_quotes'] = $parseInfo['no_quotes'];
        }
        $result['direction'] = $parseInfo['dir'];
        return $result;
    }

    public function process($tokens, $select = array()) {
        $out = array();
        $parseInfo = $this->initParseInfo();

        if (!$tokens) {
            return false;
        }

        foreach ($tokens as $token) {
            $upper = strtoupper(trim($token));
            switch ($upper) {
            case ',':
                $out[] = $this->processOrderExpression($parseInfo, $select);
                $parseInfo = $this->initParseInfo();
                break;

            case 'DESC':
                $parseInfo['dir'] = "DESC";
                break;

            case 'ASC':
                $parseInfo['dir'] = "ASC";
                break;

            default:
                $parseInfo['base_expr'] .= $token;
            }
        }

        $out[] = $this->processOrderExpression($parseInfo, $select);
        return $out;
    }
}
?>
