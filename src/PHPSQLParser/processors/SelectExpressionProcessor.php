<?php


namespace PHPSQLParser\processors;
use PHPSQLParser\utils\ExpressionType;

require_once(dirname(__FILE__) . '/AbstractProcessor.php');
require_once(dirname(__FILE__) . '/ExpressionListProcessor.php');
require_once(dirname(__FILE__) . '/../utils/ExpressionType.php');


class SelectExpressionProcessor extends AbstractProcessor {

    protected function processExpressionList($unparsed) {
        $processor = new ExpressionListProcessor();
        return $processor->process($unparsed);
    }

    /**
     * This fuction processes each SELECT clause.
     * We determine what (if any) alias
     * is provided, and we set the type of expression.
     */
    public function process($expression) {
        $tokens = $this->splitSQLIntoTokens($expression);
        $token_count = count($tokens);
        if ($token_count === 0) {
            return null;
        }

        /*
         * Determine if there is an explicit alias after the AS clause.
         * If AS is found, then the next non-whitespace token is captured as the alias.
         * The tokens after (and including) the AS are removed.
         */
        $base_expr = "";
        $stripped = array();
        $capture = false;
        $alias = false;
        $processed = false;

        for ($i = 0; $i < $token_count; ++$i) {
            $token = $tokens[$i];
            $upper = strtoupper($token);

            if ($upper === 'AS') {
                $alias = array('as' => true, "name" => "", "base_expr" => $token);
                $tokens[$i] = "";
                $capture = true;
                continue;
            }

            if (!$this->isWhitespaceToken($upper)) {
                $stripped[] = $token;
            }

            // we have an explicit AS, next one can be the alias
            // but also a comment!
            if ($capture) {
                if (!$this->isWhitespaceToken($upper) && !$this->isCommentToken($upper)) {
                    $alias['name'] .= $token;
                    array_pop($stripped);
                }
                $alias['base_expr'] .= $token;
                $tokens[$i] = "";
                continue;
            }

            $base_expr .= $token;
        }

        if ($alias) {
            // remove quotation from the alias
            $alias['no_quotes'] = $this->revokeQuotation($alias['name']);
            $alias['name'] = trim($alias['name']);
            $alias['base_expr'] = trim($alias['base_expr']);
        }

        $stripped = $this->processExpressionList($stripped);

        // TODO: the last part can also be a comment, don't use array_pop

        // we remove the last token, if it is a colref,
        // it can be an alias without an AS
        $last = array_pop($stripped);
        if (!$alias && $this->isColumnReference($last)) {

            // TODO: it can be a comment, don't use array_pop

            // check the token before the colref
            $prev = array_pop($stripped);

            if ($this->isReserved($prev) || $this->isConstant($prev) || $this->isAggregateFunction($prev)
                    || $this->isFunction($prev) || $this->isExpression($prev) || $this->isSubQuery($prev)
                    || $this->isColumnReference($prev) || $this->isBracketExpression($prev)|| $this->isCustomFunction($prev)) {

                $alias = array('as' => false, 'name' => trim($last['base_expr']),
                               'no_quotes' => $this->revokeQuotation($last['base_expr']),
                               'base_expr' => trim($last['base_expr']));
                // remove the last token
                array_pop($tokens);
            }
        }

        $base_expr = $expression;

        // TODO: this is always done with $stripped, how we do it twice?
        $processed = $this->processExpressionList($tokens);

        // if there is only one part, we copy the expr_type
        // in all other cases we use "EXPRESSION" as global type
        $type = ExpressionType::EXPRESSION;
        if (count($processed) === 1) {
            if (!$this->isSubQuery($processed[0])) {
                $type = $processed[0]['expr_type'];
                $base_expr = $processed[0]['base_expr'];
                $no_quotes = isset($processed[0]['no_quotes']) ? $processed[0]['no_quotes'] : null;
                $processed = $processed[0]['sub_tree']; // it can be FALSE
            }
        }

        $result = array();
        $result['expr_type'] = $type;
        $result['alias'] = $alias;
        $result['base_expr'] = trim($base_expr);
        if (!empty($no_quotes)) {
            $result['no_quotes'] = $no_quotes;
        }
        $result['sub_tree'] = (empty($processed) ? false : $processed);
        return $result;
    }

}
?>
