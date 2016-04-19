<?php

/**
 * you cannot execute this script within Eclipse PHP
 * because of the limited output buffer. Try to run it
 * directly within a shell.
 */

namespace PHPSQLParser;

require_once dirname(__FILE__).'/src/PHPSQLParser/PHPSQLParser.php';

///////// TESTCASE

// $sql = 'SELECT * FROM USER WHERE ID IN (SELECT ID FROM ASD WHERE ID NOT IN (SELECT ID FROM BADUSER WHERE ID LIKE "FUCKING BAD"))';

// $sql = 'SELECT nhanvien.manhanvien, nhanvien.tennv, nhanhvien.maphong, phong.maphong, phong.makhuvuc
// FROM qlns.nhanvien, qlns.phong
// WHERE nhanvien.maphong = phong.maphong';

// $sql = "SELECT MaTaiSan AS MA, TenTaiSan AS TAI_SAN, DonVi AS PHAN_PHOI, Gia AS GIA
// FROM CONGTY,TAI_SAN,PHAN_PHOI WHERE (MaTaiSan IN
// (SELECT MaTaiSan
// FROM TaiSan WHERE MaTaiSan= 'Con Heo'))
// AND ((TAI_SAN.MaTaiSan=PHAN_PHOI.MaTaiSan) AND (PH.MaPhong=PHAN_PHOI.MaPhong))";

// $sql = "SELECT sv.* from SV, Lop
// WHERE sv.malop = lop.malop
// AND tenlop LIKE 'Lop 10A1'";

// $sql = "SELECT sv.*
// FROM SV INNER JOIN LOP ON SV.MALOP = LOP.MALOP
// WHERE tenlop LIKE 'Lop 10A1'";

// $sql = "SELECT nhanvien.* from nhanvien RIGHT JOIN phong ON nhanvien.maphong = phong.maphong WHERE PHONG.MAPHONG = 'ASD'";

// $sql = "SELECT max(Diem), min(Diem), avg(Diem) FROM KETQUATHI WHERE MAMH='CSDL'";

// $sql = "SELECT MAX(DIEM), MIN(DIEM), AVG(DIEM) FROM KQT GROUP BY MAMH, MASV";

// $sql = "SELECT COUNT(KETQUA.MAMH)
// FROM MONHOC, KETQUA
// WHERE MONHOC.MAMH = KETQUA.MAMH
// GROUP BY MONHOC.MAMH";

// $sql = "SELECT * FROM MONHOC
// WHERE MAMH IN (SELECT MAMH FROM KETQUA)";

// $sql = "select mahv from ketquathi, sv
// where (count(mahv) = (select count(mamh) from monhoc)) and (sv.mahv = ketquathi.mahv) or (mahv = (select mahv from sv where mahv = '123'))";

// $sql = "SELECT mahv, masv FROM SINHVIEN,LOP WHERE MALOP='10A1'";
// $sql = "SELECT count(mahv), max(masv) FROM SINHVIEN,LOP WHERE MALOP='10A1'";
// $sql = "SELECT * FROM SINHVIEN WHERE MALOP='10A1'";

//////////////// END TESTCASE

define('PI', '&pi;'); // π

define('SIGMA', '&sigma;'); // σ

define('TAU', '&image;'); // τ

define('AND_OP', '&and;'); // ∧

define('OR_OP', '&or;'); // ∨

define('JOIN', '&#10781;'); //⨝

define('LEFT_JOIN', '&#10197;'); // ⟕

define('RIGHT_JOIN', '&#10198;'); // ⟖

// CSS-HTML CONTENT
define('FONT_SMALL', 'class="small">');

define('FONT_SMALLER', 'class="smaller">');

define('FONT_NONE', '>');

define('SPAN_START', '<span ');

define('SPAN_END', '</span>');

//usage: SPAN_START . FONT_SMALL/FONT_SMALLER . CONTENT . SPAN_END;

function showResult($s)
{
    print_r($s);
}

function isValidInput($raw)
{
    return is_array($raw) || is_object($raw);
}

function test($raw, $result, $count)
{
    if (isValidInput($raw)) {
        foreach ($raw as $key => $val) {
            echo $key."\t val: ".$val."\t recurrence count: ".$count."\n";
            convert($val, $result, $count + 1);
        }
    }
  // return;
}

function getExprType($input)
{
    return $input['expr_type'];
}

function getBaseExpr($part)
{
    return $part['base_expr'];
}

function getValue($part)
{
    return getBaseExpr($part);
}

function isPartHasType($part, $type)
{
    return getExprType($part) == $type;
}

///////
function isAggregateFunc($part)
{
    return isPartHasType($part, 'aggregate_function');
}

function isColRef($part)
{
    return isPartHasType($part, 'colref');
}

//input: $part = WHERE[0]
function getCofref($part)
{
    $parent = $part['no_quotes']['parts'];

    return end($parent);
}

//input: raw[WHERE]

function isTable($part)
{
    return isPartHasType($part, 'table');
}

function hasJoinType($table, $string)
{
    return $table['join_type'] == $string;
}

function isBracketExpr($part)
{
    return isPartHasType($part, 'bracket_expression');
}

function isSubQuery($part)
{
    return isPartHasType($part, 'subquery');
}

function isOperator($part)
{
    return isPartHasType($part, 'operator');
}

function getOp($part)
{
    $string = getValue($part);
    $except = array('=', '!=', 'LIKE', 'NOT', 'IN');
    if (in_array($string, $except)) {
        return $string;
    } elseif ($string == 'AND') {
        return AND_OP;
    } else {
        return OR_OP;
    }
}

//input: raw[0] => Table
function getTableName($part)
{
    return $part['table'];
}

function lastIndex($input)
{
    return count($input) - 1;
}

function isSameCofref($input)
{
    if (!isColRef($input['0']) || !isColRef($input['2'])) {
        return false;
    }

    foreach ($input as $key => $value) {
        if (isColRef($value)) {
            $ref[$key] = getCofref($value);
      // echo $ref[$key]."\n";
        }
        if (isOperator($value)) {
            $op = getOp($value);
      // echo $op;
        }
    }
  // print_r($ref);
  return ($ref['0'] == $ref['2']) && $op == '=';
}

//input raw[SELECT]
function selectToString($input)
{
    if (isSelectAll($input)) {
        return '';
    }

    $i = -1;
    foreach ($input as $part) {
        ++$i;
        $result .= refToString($part, lastIndex($input) == $i);
    }

    return '('.$result.')';
}

//input raw[FROM]
function fromToString($input)
{
    $i = -1;
    foreach ($input as $part) {
        ++$i;
        if ($i > 0) {
            $joinType = makeJoinSymbol($part);
            $tables .= $joinType;
        }
        if (isTable($part)) {
            $tables .= getTableName($part);
        }
    }
    $result = $tables;

    return $result;
}

// input: raw[WHERE], must to use recur
function whereToString($input)
{
    // if(isSameCofref($input)) return;
  foreach ($input as $key => $value) {
      if (isBracketExpr($value)) {
          $pilot[$key] = whereToString($value['sub_tree']);
      } elseif (isSubQuery($value)) {
          $pilot[$key] = sqlToString($value['sub_tree']);
      } elseif (isOperator($value)) {
          $pilot[$key] = ' '.getOp($value).' ';
      } else {
          $pilot[$key] = refToString($value, true);
      }
  }

    foreach ($pilot as $value) {
        $result .= $value;
    }

    return '('.$result.')';
}

// input: raw[SELECT]
function makeSelectSymbol($input)
{
    $result;
  // print(isSelectAll($input));
  if (isSelectAll($input)) {
      return '';
  } elseif (isSelectAggregateFunc($input)) {
      $result = TAU;
  } else {
      $result = PI;
  }
  //add CSS class to minimize select item here ?
  return $result;
}

function makeJoinSymbol($part)
{
    if (hasJoinType($part, 'RIGHT')) {
        return RIGHT_JOIN;
    } elseif (hasJoinType($part, 'LEFT')) {
        return LEFT_JOIN;
    } else {
        return JOIN;
    }
}

function groupToString($input)
{
    $i = -1;
    foreach ($input as $part) {
        ++$i;
        $result .= refToString($part, lastIndex($input) == $i);
    }

    return $result;
}

// input: raw[SELECT]
function isSelectAll($part)
{
    return isColRef($part['0']) && getBaseExpr($part['0']) == '*';
}

function isSelectAggregateFunc($part)
{
    return isAggregateFunc($part[0]);
}

function refToString($part, $isLast)
{
    if (isColRef($part)) {
        return colrefToString($part, $isLast);
    } elseif (isAggregateFunc($part)) {
        return aggrFuncToString($part, $isLast);
    } else {
        return getBaseExpr($part);
    }
}

function colrefToString($part, $isLast)
{
    $result = getBaseExpr($part);
    if (!$isLast) {
        $result .= ', ';
    }

    return $result;
}
// input: $part[expr_type] = aggregate_function;
function aggrFuncToString($part, $isLast)
{
    if (array_key_exists('sub_tree', $part)) {
        $paramList = $part['sub_tree'];
    }

    $i = -1;
    foreach ($paramList as $paramArr) {
        ++$i;
        $param .= getBaseExpr($paramArr);
        if (!(lastIndex($paramList) == $i)) {
            $param .= ', ';
        }
    }
    $func = getBaseExpr($part).'('.$param.')';

    $result = $func;
    if (!$isLast) {
        $result .= ', ';
    }

    return $result;
}

//////
//$size == 0: FONT_NONE
//$size == 1: FONT_SMALLER
//$size == 2: FONT_SMALL
function makeHTML($content, $size = 2)
{
    if ($size == 2) {
        $fontSize = FONT_SMALL;
    } elseif ($size == 1) {
        $fontSize = FONT_SMALLER;
    } else {
        $fontSize = FONT_NONE;
    }

    return SPAN_START.$fontSize.$content.SPAN_END;
}

$resultCount;
$resultList;
function addResult($string, $resultList)
{
}

///////
function sqlToString($raw)
{
    if (isValidInput($raw)) {
        foreach ($raw as $key => $val) {
            if ($key == 'SELECT') {
                $child = selectToString($val);
                $childContent = makeHTML($child);
                $symbol = makeSelectSymbol($val);
                $select = makeHTML($symbol.$childContent, 0);
            } elseif ($key == 'FROM') {
                $child = '('.fromToString($val).')';
                $from = makeHTML($child, 0);
            } elseif ($key == 'WHERE') {
                $child = whereToString($val);
                if ($child) {
                    $childContent = makeHTML($child);
                    $where = makeHTML(SIGMA.$childContent, 0);
                }
            } elseif ($key == 'GROUP') {
                $child = '('.groupToString($val).')';
                $group = makeHTML($child);
            }
        }
        $result = $group.$select.$where.$from;
    }

    return $result;
}
////

// echo "Input: ".$sql."\n";
// showResult($parser->parsed);
// print_r($parser->parsed);

// $select = selectToString($raw['SELECT']);
// $from = fromToString($raw['FROM']);
// $where = whereTostring($raw['WHERE']);

// test($raw,$result, 0);

// echo "select: ". $select . "\n";
// echo "from: ". $from ."\n";
// echo "where: ". $where ."\n";

///////
// $parser = new PHPSQLParser($sql);
// $raw = $parser->parsed;

// print_r($raw);
// $result = sqlToString($raw);

// echo $result;
// echo "\n";

if (isset($_POST['submit'])) {
    $sql = $_POST['sql-statement'];
    $parser = new PHPSQLParser($sql);
    $raw = $parser->parsed;
    $result = sqlToString($raw);
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>SQL2RA</title>
    <!-- link(rel="stylesheet", href="http://fonts.googleapis.com/icon?family=Material+Icons")-->
    <link rel="stylesheet" href="assets/components/materialize/css/materialize.min.css">
    <link rel="stylesheet" href="assets/components/font-awesome-4.5.0/css/font-awesome.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  </head>
  <body>
    <main class="container">
      <div class="row">
        <div class="col s8 offset-s2">
          <div class="row">
            <div class="col s12">
              <h3 class="center-align">SQL To Relational Algebra</h3>
            </div>
          </div>
          <div class="row">
            <form class="col s12" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
              <div class="row">
                <div class="input-field col s12">
                  <i class="material-icons prefix">code</i>
                  <textarea id="sql-statement" name="sql-statement" class="materialize-textarea"></textarea>
                  <label for="sql-statement">SQL Statement</label>
                </div>
              </div>
              <div class="input-field">
                <button type="submit" name="submit" class="btn-large waves-effect waves-light" style="width:100%;"><i class="material-icons">repeat</i></button>
              </div>
            </form>
          </div>
          <div class="result-container">
          <?php
            // $parser = new PHPSQLParser($sql);
            // $raw = $parser->parsed;
            // $result = sqlToString($raw);
            // echo $sql ."\n";
            if (isset($_POST['submit'])) {
                echo $result;
                echo "\n";
            }
          ?>
          </div>
        </div>
      </div>
    </main>
    <footer class="page-footer">
      <p class="center-align red-text text-lighten-5">Coded with <i class="fa fa-heart"></i> by Hung</p>
    </footer>
    <!-- script(type="application/javascript", src= compDir + "jquery-2.2.1.min.js")-->
    <script type="application/javascript" src="assets/components/jquery-2.1.4.min.js"></script>
    <script type="application/javascript" src="assets/components/materialize/js/materialize.min.js"></script>
    <script type="application/javascript" src="assets/components/jquery.validate.min.js"></script>
    <script type="application/javascript" src="assets/components/additional-methods.min.js"></script>
    <script type="application/javascript" src="assets/js/main.js"></script>
  </body>
</html>
