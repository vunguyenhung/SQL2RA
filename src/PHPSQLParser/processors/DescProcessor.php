<?php

namespace PHPSQLParser\processors;
require_once(dirname(__FILE__) . '/ExplainProcessor.php');

class DescProcessor extends ExplainProcessor {

    protected function isStatement($keys, $needle = "DESC") {
        return parent::isStatement($keys, $needle);
    }
}
?>
