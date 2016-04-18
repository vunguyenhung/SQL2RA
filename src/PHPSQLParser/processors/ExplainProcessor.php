<?php

namespace PHPSQLParser\processors;
use PHPSQLParser\utils\ExpressionType;

require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/../utils/ExpressionType.php';


class ExplainProcessor extends AbstractProcessor {

    protected function isStatement($keys, $needle = "EXPLAIN") {
        $pos = array_search($needle, $keys);
        if (isset($keys[$pos + 1])) {
            return in_array($keys[$pos + 1], array('SELECT', 'DELETE', 'INSERT', 'REPLACE', 'UPDATE'), true);
        }
        return false;
    }

    // TODO: refactor that function
    public function process($tokens, $keys = array()) {

        $base_expr = "";
        $expr = array();
        $currCategory = "";

        if ($this->isStatement($keys)) {
            foreach ($tokens as $token) {

                $trim = trim($token);
                $base_expr .= $token;

                if ($trim === '') {
                    continue;
                }

                $upper = strtoupper($trim);

                switch ($upper) {

                case '=':
                    if ($currCategory === 'FORMAT') {
                        $expr[] = array('expr_type' => ExpressionType::OPERATOR, 'base_expr' => $trim);
                    }
                    // else?
                    break;


                default:
                // ignore the other stuff
                    break;
                }
            }
            return empty($expr) ? null : $expr;
        }

        foreach ($tokens as $token) {

            $trim = trim($token);

            if ($trim === '') {
                continue;
            }

            switch ($currCategory) {

            case '':
                $currCategory = 'TABLENAME';
                $expr[] = array('expr_type' => ExpressionType::TABLE, 'table' => $trim,
                                'no_quotes' => $this->revokeQuotation($trim), 'alias' => false, 'base_expr' => $trim);
                break;

            default:
                break;
            }
        }
        return empty($expr) ? null : $expr;
    }
}

?>
