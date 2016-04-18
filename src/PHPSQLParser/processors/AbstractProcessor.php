<?php


namespace PHPSQLParser\processors;

use PHPSQLParser\lexer\PHPSQLLexer;
use PHPSQLParser\utils\ExpressionType;

require_once dirname(__FILE__) . '/../utils/ExpressionType.php';
require_once dirname(__FILE__) . '/../lexer/PHPSQLLexer.php';


abstract class AbstractProcessor {

    /**
     * This function implements the main functionality of a processor class.
     * Always use default valuses for additional parameters within overridden functions.
     */
    public abstract function process($tokens);

    /**
     * this function splits up a SQL statement into easy to "parse"
     * tokens for the SQL processor
     */
    public function splitSQLIntoTokens($sql) {
        $lexer = new PHPSQLLexer();
        return $lexer->split($sql);
    }

    /**
     * Revokes the quoting characters from an expression
     * Possibibilies:
     *   `a`
     *   'a'
     *   "a"
     *   `a`.`b`
     *   `a.b`
     *   a.`b`
     *   `a`.b
     * It is also possible to have escaped quoting characters
     * within an expression part:
     *   `a``b` => a`b
     * And you can use whitespace between the parts:
     *   a  .  `b` => [a,b]
     */
    protected function revokeQuotation($sql) {
        $tmp = trim($sql);
        $result = array();

        $quote = false;
        $start = 0;
        $i = 0;
        $len = strlen($tmp);

        while ($i < $len) {

            $char = $tmp[$i];
            switch ($char) {
            case '`':
            case '\'':
            case '"':
                if ($quote === false) {
                    // start
                    $quote = $char;
                    $start = $i + 1;
                    break;
                }
                if ($quote !== $char) {
                    break;
                }
                if (isset($tmp[$i + 1]) && ($quote === $tmp[$i + 1])) {
                    // escaped
                    $i++;
                    break;
                }
                // end
                $char = substr($tmp, $start, $i - $start);
                $result[] = str_replace($quote . $quote, $quote, $char);
                $start = $i + 1;
                $quote = false;
                break;

            case '.':
                if ($quote === false) {
                    // we have found a separator
                    $char = trim(substr($tmp, $start, $i - $start));
                    if ($char !== '') {
                        $result[] = $char;
                    }
                    $start = $i + 1;
                }
                break;

            default:
            // ignore
                break;
            }
            $i++;
        }

        if ($quote === false && ($start < $len)) {
            $char = trim(substr($tmp, $start, $i - $start));
            if ($char !== '') {
                $result[] = $char;
            }
        }

        return array('delim' => (count($result) === 1 ? false : '.'), 'parts' => $result);
    }

    /**
     * This method removes parenthesis from start of the given string.
     * It removes also the associated closing parenthesis.
     */
    protected function removeParenthesisFromStart($token) {
        $parenthesisRemoved = 0;

        $trim = trim($token);
        if ($trim !== '' && $trim[0] === '(') { // remove only one parenthesis pair now!
            $parenthesisRemoved++;
            $trim[0] = ' ';
            $trim = trim($trim);
        }

        $parenthesis = $parenthesisRemoved;
        $i = 0;
        $string = 0;
        while ($i < strlen($trim)) {

            if ($trim[$i] === "\\") {
                $i += 2; // an escape character, the next character is irrelevant
                continue;
            }

            if ($trim[$i] === "'" || $trim[$i] === '"') {
                $string++;
            }

            if (($string % 2 === 0) && ($trim[$i] === '(')) {
                $parenthesis++;
            }

            if (($string % 2 === 0) && ($trim[$i] === ')')) {
                if ($parenthesis == $parenthesisRemoved) {
                    $trim[$i] = ' ';
                    $parenthesisRemoved--;
                }
                $parenthesis--;
            }
            $i++;
        }
        return trim($trim);
    }

    protected function getVariableType($expression) {
        // $expression must contain only upper-case characters
        if ($expression[1] !== '@') {
            return ExpressionType::USER_VARIABLE;
        }

        $type = substr($expression, 2, strpos($expression, '.', 2));

        switch ($type) {
        case 'GLOBAL':
            $type = ExpressionType::GLOBAL_VARIABLE;
            break;
        case 'LOCAL':
            $type = ExpressionType::LOCAL_VARIABLE;
            break;
        case 'SESSION':
        default:
            $type = ExpressionType::SESSION_VARIABLE;
            break;
        }
        return $type;
    }

    protected function isCommaToken($token) {
        return (trim($token) === ',');
    }

    protected function isWhitespaceToken($token) {
        return (trim($token) === '');
    }

    protected function isCommentToken($token) {
        return isset($token[0]) && isset($token[1])
                && (($token[0] === '-' && $token[1] === '-') || ($token[0] === '/' && $token[1] === '*'));
    }

    protected function isColumnReference($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::COLREF);
    }

    protected function isReserved($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::RESERVED);
    }

    protected function isConstant($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::CONSTANT);
    }

    protected function isAggregateFunction($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::AGGREGATE_FUNCTION);
    }

    protected function isCustomFunction($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::CUSTOM_FUNCTION);
    }

    protected function isFunction($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::SIMPLE_FUNCTION);
    }

    protected function isExpression($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::EXPRESSION);
    }

    protected function isBracketExpression($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::BRACKET_EXPRESSION);
    }

    protected function isSubQuery($out) {
        return (isset($out['expr_type']) && $out['expr_type'] === ExpressionType::SUBQUERY);
    }

    /**
     * translates an array of objects into an associative array
     */
    public function toArray($tokenList) {
        $expr = array();
        foreach ($tokenList as $token) {
            $expr[] = $token->toArray();
        }
        return $expr;
    }

    protected function array_insert_after($array, $key, $entry) {
        $idx = array_search($key, array_keys($array));
        $array = array_slice($array, 0, $idx + 1, true) + $entry
                + array_slice($array, $idx + 1, count($array) - 1, true);
        return $array;
    }
}
?>
