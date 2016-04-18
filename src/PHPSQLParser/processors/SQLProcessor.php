<?php

namespace PHPSQLParser\processors;
require_once dirname(__FILE__) . '/SQLChunkProcessor.php';

class SQLProcessor extends SQLChunkProcessor {

    /**
     * This function breaks up the SQL statement into logical sections.
     * Some sections are delegated to specialized processors.
     */
    public function process($tokens) {
        $prev_category = "";
        $token_category = "";
        $skip_next = 0;
        $out = false;

        $tokenCount = count($tokens);
        for ($tokenNumber = 0; $tokenNumber < $tokenCount; ++$tokenNumber) {

            $token = $tokens[$tokenNumber];
            $trim = trim($token); // this removes also \n and \t!

            // if it starts with an "(", it should follow a SELECT
            if ($trim !== "" && $trim[0] === "(" && $token_category === "") {
                $token_category = 'BRACKET';
                $prev_category = $token_category;
            }

            /*
             * If it isn't obvious, when $skip_next is set, then we ignore the next real token, that is we ignore whitespace.
             */
            if ($skip_next > 0) {
                if ($trim === "") {
                    if ($token_category !== "") { // is this correct??
                        $out[$token_category][] = $token;
                    }
                    continue;
                }
                // to skip the token we replace it with whitespace
                $trim = "";
                $token = "";
                $skip_next--;
                if ($skip_next > 0) {
                    continue;
                }
            }

            $upper = strtoupper($trim);
            switch ($upper) {

            /* Tokens that get their own sections. These keywords have subclauses. */
            case 'SELECT':
            case 'ORDER':
            case 'GROUP':
            case 'HAVING':
            case 'WHERE':
                $token_category = $upper;
                break;


            case 'FROM':
            // this FROM is different from FROM in other DML (not join related)
                if ($token_category === 'PREPARE') {
                    continue 2;
                }
                // no separate section
                if ($token_category === 'SHOW') {
                    continue;
                }
                $token_category = $upper;
                break;

            case 'DESC':
                if ($token_category === '') {
                    // short version of DESCRIBE
                    $token_category = $upper;
                }
                // else direction of ORDER-BY
                break;


            case 'IF':
                if ($prev_category === 'TABLE') {
                    $token_category = 'CREATE';
                    $out[$token_category] = array_merge($out[$token_category], $out[$prev_category]);
                    $out[$prev_category] = array();
                    $out[$token_category][] = $trim;
                    $prev_category = $token_category;
                    continue 2;
                }
                break;

            case 'NOT':
                if ($prev_category === 'CREATE') {
                    $token_category = $prev_category;
                    $out[$prev_category][] = $trim;
                    continue 2;
                }
                break;

            case 'EXISTS':
                if ($prev_category === 'CREATE') {
                    $out[$prev_category][] = $trim;
                    $prev_category = $token_category = 'TABLE';
                    continue 2;
                }
                break;

            case 'USING': /* USING in FROM clause is different from USING w/ prepared statement*/
                if ($token_category === 'EXECUTE') {
                    $token_category = $upper;
                    continue 2;
                }
                if ($token_category === 'FROM' && !empty($out['DELETE'])) {
                    $token_category = $upper;
                    continue 2;
                }
                break;

            // This token is ignored, except within CREATE TABLE
            case 'BY':
                if ($prev_category === 'TABLE') {
                    break;
                }
                continue 2;


            case 'USE':
                if ($token_category === 'FROM') {
                    // index hint within FROM clause
                    $out[$token_category][] = $trim;
                    continue 2;
                }
                // set the category in case these get subclauses in a future version of MySQL
                $token_category = $upper;
                $out[$upper][0] = $trim;
                continue 2;

            case 'WITH':
                if ($token_category === 'GROUP') {
                    $skip_next = 1;
                    $out['OPTIONS'][] = 'WITH ROLLUP'; // TODO: this could be generate problems within the position calculator
                    continue 2;
                }
                break;

            case 'AS':
                break;

            case '':
            case ',':
            case ';':
                break;

            default:
                break;
            }

            // remove obsolete category after union (empty category because of
            // empty token before select)
            if ($token_category !== "" && ($prev_category === $token_category)) {
                $out[$token_category][] = $token;
            }

            $prev_category = $token_category;
        }

        return parent::process($out);
    }
}
?>
