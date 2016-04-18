<?php

namespace PHPSQLParser\processors;
use PHPSQLParser\utils\ExpressionType;

require_once dirname(__FILE__) . '/AbstractProcessor.php';
require_once dirname(__FILE__) . '/ExpressionListProcessor.php';
require_once dirname(__FILE__) . '/DefaultProcessor.php';
require_once dirname(__FILE__) . '/../utils/ExpressionType.php';


class FromProcessor extends AbstractProcessor {

    protected function processExpressionList($unparsed) {
        $processor = new ExpressionListProcessor();
        return $processor->process($unparsed);
    }

    protected function processSQLDefault($unparsed) {
        $processor = new DefaultProcessor();
        return $processor->process($unparsed);
    }

    protected function initParseInfo($parseInfo = false) {
        // first init
        if ($parseInfo === false) {
            $parseInfo = array('join_type' => "", 'saved_join_type' => "JOIN");
        }
        // loop init
        return array('expression' => "", 'token_count' => 0, 'table' => "", 'no_quotes' => "", 'alias' => false,
                     'hints' => false, 'join_type' => "", 'next_join_type' => "",
                     'saved_join_type' => $parseInfo['saved_join_type'], 'ref_type' => false, 'ref_expr' => false,
                     'base_expr' => false, 'sub_tree' => false, 'subquery' => "");
    }

    protected function processFromExpression(&$parseInfo) {
        $res = array();

        // exchange the join types (join_type is save now, saved_join_type holds the next one)
        $parseInfo['join_type'] = $parseInfo['saved_join_type']; // initialized with JOIN
        $parseInfo['saved_join_type'] = ($parseInfo['next_join_type'] ? $parseInfo['next_join_type'] : 'JOIN');

        // we have a reg_expr, so we have to parse it
        if ($parseInfo['ref_expr'] !== false) {
            $unparsed = $this->splitSQLIntoTokens($parseInfo['ref_expr']);

            // here we can get a comma separated list
            foreach ($unparsed as $k => $v) {
                if ($this->isCommaToken($v)) {
                    $unparsed[$k] = "";
                }
            }
            $ref = $this->processExpressionList($unparsed);
            $parseInfo['ref_expr'] = (empty($ref) ? false : $ref);
        }

        // there is an expression, we have to parse it
        if (substr(trim($parseInfo['table']), 0, 1) == '(') {
            $parseInfo['expression'] = $this->removeParenthesisFromStart($parseInfo['table']);

            if (preg_match("/^\\s*select/i", $parseInfo['expression'])) {
                $parseInfo['sub_tree'] = $this->processSQLDefault($parseInfo['expression']);
                $res['expr_type'] = ExpressionType::SUBQUERY;
            } else {
                $tmp = $this->splitSQLIntoTokens($parseInfo['expression']);
                $parseInfo['sub_tree'] = $this->process($tmp);
                $res['expr_type'] = ExpressionType::TABLE_EXPRESSION;
            }
        } else {
            $res['expr_type'] = ExpressionType::TABLE;
            $res['table'] = $parseInfo['table'];
            $res['no_quotes'] = $this->revokeQuotation($parseInfo['table']);
        }

        $res['alias'] = $parseInfo['alias'];
        $res['hints'] = $parseInfo['hints'];
        $res['join_type'] = $parseInfo['join_type'];
        $res['ref_type'] = $parseInfo['ref_type'];
        $res['ref_clause'] = $parseInfo['ref_expr'];
        $res['base_expr'] = trim($parseInfo['expression']);
        $res['sub_tree'] = $parseInfo['sub_tree'];
        return $res;
    }

    public function process($tokens) {
        $parseInfo = $this->initParseInfo();
        $expr = array();
        $token_category = '';
        $prevToken = '';

        $skip_next = false;
        $i = 0;

        foreach ($tokens as $token) {
            $upper = strtoupper(trim($token));

            if ($skip_next && $token !== "") {
                $parseInfo['token_count']++;
                $skip_next = false;
                continue;
            } else {
                if ($skip_next) {
                    continue;
                }
            }

            switch ($upper) {
            case 'CROSS':
            case ',':
            case 'INNER':
                break;

            case 'OUTER':
            case 'JOIN':
                if ($token_category === 'LEFT' || $token_category === 'RIGHT') {
                    $token_category = '';
                    $parseInfo['next_join_type'] = strtoupper(trim($prevToken)); // it seems to be a join
                }
                break;

            case 'LEFT':
            case 'RIGHT':
                $token_category = $upper;
                $prevToken = $token;
                $i++;
                continue 2;

            default:
                if ($token_category === 'LEFT' || $token_category === 'RIGHT') {
                    if ($upper === '') {
                        $prevToken .= $token;
                        break;
                    } else {
                        $token_category = '';     // it seems to be a function
                        $parseInfo['expression'] .= $prevToken;
                        if ($parseInfo['ref_type'] !== false) { // all after ON / USING
                            $parseInfo['ref_expr'] .= $prevToken;
                        }
                        $prevToken = '';
                    }
                }
                $parseInfo['expression'] .= $token;
                if ($parseInfo['ref_type'] !== false) { // all after ON / USING
                    $parseInfo['ref_expr'] .= $token;
                }
                break;
            }

            if ($upper === '') {
                $i++;
                continue;
            }

            switch ($upper) {
            case 'AS':
                $parseInfo['alias'] = array('as' => true, 'name' => "", 'base_expr' => $token);
                $parseInfo['token_count']++;
                $n = 1;
                $str = "";
                while ($str === "") {
                    $parseInfo['alias']['base_expr'] .= ($tokens[$i + $n] === "" ? " " : $tokens[$i + $n]);
                    $str = trim($tokens[$i + $n]);
                    ++$n;
                }
                $parseInfo['alias']['name'] = $str;
                $parseInfo['alias']['no_quotes'] = $this->revokeQuotation($str);
                $parseInfo['alias']['base_expr'] = trim($parseInfo['alias']['base_expr']);
                continue;


            case 'USING':
            case 'ON':
                $parseInfo['ref_type'] = $upper;
                $parseInfo['ref_expr'] = "";

            case 'CROSS':
            case 'INNER':
            case 'OUTER':
                $parseInfo['token_count']++;
                continue;

            case ',':
                $parseInfo['next_join_type'] = 'CROSS';

            case 'JOIN':
                if ($parseInfo['subquery']) {
                    $parseInfo['sub_tree'] = $this->parse($this->removeParenthesisFromStart($parseInfo['subquery']));
                    $parseInfo['expression'] = $parseInfo['subquery'];
                }

                $expr[] = $this->processFromExpression($parseInfo);
                $parseInfo = $this->initParseInfo($parseInfo);
                break;

            default:
            // TODO: enhance it, so we can have base_expr to calculate the position of the keywords
            // build a subtree under "hints"
                if ($token_category === 'IDX_HINT') {
                    $token_category = '';
                    $cur_hint = (count($parseInfo['hints']) - 1);
                    $parseInfo['hints'][$cur_hint]['hint_list'] = $token;
                    continue;
                }

                if ($parseInfo['token_count'] === 0) {
                    if ($parseInfo['table'] === "") {
                        $parseInfo['table'] = $token;
                        $parseInfo['no_quotes'] = $this->revokeQuotation($token);
                    }
                } else if ($parseInfo['token_count'] === 1) {
                    $parseInfo['alias'] = array('as' => false, 'name' => trim($token),
                                                'no_quotes' => $this->revokeQuotation($token),
                                                'base_expr' => trim($token));
                }
                $parseInfo['token_count']++;
                break;
            }
            $i++;
        }

        $expr[] = $this->processFromExpression($parseInfo);
        return $expr;
    }

}

?>
