<?php

namespace PHPSQLParser\processors;
require_once(dirname(__FILE__) . '/OrderByProcessor.php');


class GroupByProcessor extends OrderByProcessor {

    public function process($tokens, $select = array()) {
        $out = array();
        $parseInfo = $this->initParseInfo();

        if (!$tokens) {
            return false;
        }

        foreach ($tokens as $token) {
            $trim = strtoupper(trim($token));
            switch ($trim) {
            case ',':
                $parsed = $this->processOrderExpression($parseInfo, $select);
                unset($parsed['direction']);

                $out[] = $parsed;
                $parseInfo = $this->initParseInfo();
                break;
            default:
                $parseInfo['base_expr'] .= $token;
            }
        }

        $parsed = $this->processOrderExpression($parseInfo, $select);
        unset($parsed['direction']);
        $out[] = $parsed;

        return $out;
    }
}
?>
