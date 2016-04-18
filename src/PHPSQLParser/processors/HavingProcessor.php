<?php
namespace PHPSQLParser\processors;
use PHPSQLParser\utils\ExpressionType;

require_once dirname(__FILE__) . '/ExpressionListProcessor.php';
require_once dirname(__FILE__) . '/../utils/ExpressionType.php';

class HavingProcessor extends ExpressionListProcessor {

    public function process($tokens, $select = array()) {
        $parsed = parent::process($tokens);

        foreach ($parsed as $k => $v) {
            if ($v['expr_type'] === ExpressionType::COLREF) {
                foreach ($select as $clause) {
                    if (!$clause['alias']) {
                        continue;
                    }

                    if ($clause['alias']['no_quotes'] === $v['no_quotes']) {
                        $parsed[$k]['expr_type'] = ExpressionType::ALIAS;
                        break;
                    }
                }
            }
        }

        return $parsed;
    }
}

?>
