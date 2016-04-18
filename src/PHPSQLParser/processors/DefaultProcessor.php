<?php

namespace PHPSQLParser\processors;
require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/UnionProcessor.php';
require_once dirname(__FILE__) . '/SQLProcessor.php';


class DefaultProcessor extends AbstractProcessor {

    protected function isUnion($tokens) {
        return UnionProcessor::isUnion($tokens);
    }

    protected function processUnion($tokens) {
        // this is the highest level lexical analysis. This is the part of the
        // code which finds UNION and UNION ALL query parts
        $processor = new UnionProcessor();
        return $processor->process($tokens);
    }

    protected function processSQL($tokens) {
        $processor = new SQLProcessor();
        return $processor->process($tokens);
    }

    public function process($sql) {

        $inputArray = $this->splitSQLIntoTokens($sql);
        $queries = $this->processUnion($inputArray);

        // If there was no UNION or UNION ALL in the query, then the query is
        // stored at $queries[0].
        if (!empty($queries) && !$this->isUnion($queries)) {
            $queries = $this->processSQL($queries[0]);
        }

        return $queries;
    }

    public function revokeQuotation($sql) {
        return parent::revokeQuotation($sql);
    }
}

?>
