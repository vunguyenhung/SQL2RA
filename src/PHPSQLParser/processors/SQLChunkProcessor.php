<?php
namespace PHPSQLParser\processors;
require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/FromProcessor.php';
require_once dirname(__FILE__) . '/GroupByProcessor.php';
require_once dirname(__FILE__) . '/UsingProcessor.php';
require_once dirname(__FILE__) . '/DescProcessor.php';
require_once dirname(__FILE__) . '/HavingProcessor.php';
require_once dirname(__FILE__) . '/SelectExpressionProcessor.php';
require_once dirname(__FILE__) . '/WhereProcessor.php';
require_once dirname(__FILE__) . '/SelectProcessor.php';
require_once dirname(__FILE__) . '/OrderByProcessor.php';
require_once dirname(__FILE__) . '/BracketProcessor.php';

class SQLChunkProcessor extends AbstractProcessor {

    protected function moveLIKE(&$out) {
        if (!isset($out['TABLE']['like'])) {
            return;
        }
        $out = $this->array_insert_after($out, 'TABLE', array('LIKE' => $out['TABLE']['like']));
        unset($out['TABLE']['like']);
    }

    public function process($out) {
        if (!$out) {
            return false;
        }
        if (!empty($out['BRACKET'])) {
            // TODO: this field should be a global STATEMENT field within the output
            // we could add all other categories as sub_tree, it could also work with multipe UNIONs
            $processor = new BracketProcessor();
            $out['BRACKET'] = $processor->process($out['BRACKET']);
        }
        if (!empty($out['DESC'])) {
            $processor = new DescProcessor();
            $out['DESC'] = $processor->process($out['DESC'], array_keys($out));
        }
        if (!empty($out['SELECT'])) {
            $processor = new SelectProcessor();
            $out['SELECT'] = $processor->process($out['SELECT']);
        }
        if (!empty($out['FROM'])) {
            $processor = new FromProcessor();
            $out['FROM'] = $processor->process($out['FROM']);
        }
        if (!empty($out['USING'])) {
            $processor = new UsingProcessor();
            $out['USING'] = $processor->process($out['USING']);
        }
        if (!empty($out['GROUP'])) {
            // set empty array if we have partial SQL statement
            $processor = new GroupByProcessor();
            $out['GROUP'] = $processor->process($out['GROUP'], isset($out['SELECT']) ? $out['SELECT'] : array());
        }
        if (!empty($out['ORDER'])) {
            // set empty array if we have partial SQL statement
            $processor = new OrderByProcessor();
            $out['ORDER'] = $processor->process($out['ORDER'], isset($out['SELECT']) ? $out['SELECT'] : array());
        }
        if (!empty($out['WHERE'])) {
            $processor = new WhereProcessor();
            $out['WHERE'] = $processor->process($out['WHERE']);
        }
        if (!empty($out['HAVING'])) {
            $processor = new HavingProcessor();
            $out['HAVING'] = $processor->process($out['HAVING'], isset($out['SELECT']) ? $out['SELECT'] : array());
        }
        return $out;
    }
}
?>
