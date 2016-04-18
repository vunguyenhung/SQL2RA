<?php

/**
 * you cannot execute this script within Eclipse PHP
 * because of the limited output buffer. Try to run it
 * directly within a shell.
 */

namespace PHPSQLParser;
require_once dirname(__FILE__) . '/src/PHPSQLParser/PHPSQLParser.php';

///////// TESTCASE

// $sql = 'SELECT * FROM USER WHERE ID IN (SELECT ID FROM ASD WHERE ID NOT IN (SELECT ID FROM BADUSER WHERE ID LIKE "FUCKING BAD"))';

// $sql = 'SELECT nhanvien.manhanvien, nhanvien.tennv, nhanhvien.maphong, phong.maphong, phong.makhuvuc
// FROM qlns.nhanvien, qlns.phong
// WHERE nhanvien.maphong = phong.maphong';

// $sql = "SELECT MaTaiSan AS MA, TenTaiSan AS TAI_SAN, DonVi AS PHAN_PHOI, Gia AS GIA
// FROM CONGTY WHERE (MaTaiSan IN
// (SELECT MaTaiSan
// FROM TaiSan WHERE MaTaiSan= 'Con Heo'))
// AND ((TAI_SAN.MaTaiSan=PHAN_PHOI.MaTaiSan) AND (PH.MaPhong=PHAN_PHOI.MaPhong))";

$sql = "SELECT sv.* from SV, Lop
WHERE sv.malop = lop.malop
AND tenlop LIKE 'Lop 10A1'";

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
//
// $sql = "SELECT MAHV FROM KETQUATHI, SV
// WHERE (COUNT(MAHV) = (SELECT COUNT(MAMH) FROM MONHOC)) AND (SV.MAHV = KETQUATHI.MAHV) OR (MAHV = (SELECT MAHV FROM SV WHERE MAHV = '123'))";

// $sql = "SELECT mahv, masv FROM SINHVIEN,LOP WHERE MALOP='10A1'";
// $sql = "SELECT count(mahv), max(masv) FROM SINHVIEN,LOP WHERE MALOP='10A1'";
// $sql = "SELECT * FROM SINHVIEN WHERE MALOP='10A1'";

//////////////// END TESTCASE

define("PI", "&pi;"); // π

define("SIGMA", "&sigma;"); // σ

define("TAU", "&tau;"); // τ

define("AND_OP", "&and;"); // ∧

define("OR_OP", "&or;"); // ∨

define("JOIN", "&#10781;"); //⨝

define("LEFT_JOIN", "&#10197;"); // ⟕

define("RIGHT_JOIN", "&#10198;"); // ⟖

function showResult($s){
  print_r($s);
}

function isValidInput($raw){
  return is_array($raw) || is_object($raw);
}

function test($raw, $result, $count){
  if(isValidInput($raw))

    foreach ($raw as $key => $val) {
      echo $key . "\t val: ". $val ."\t recurrence count: ". $count. "\n";
      convert($val, $result, $count + 1);
    }
  // return;
}

function getExprType($input){
  return $input['expr_type'];
}

function getBaseExpr($part){
  return $part['base_expr'];
}

function getValue($part){
  return getBaseExpr($part);
}

function isPartHasType($part, $type){
  return getExprType($part) == $type;
}


///////
function isAggregateFunc($part){
  return isPartHasType($part, "aggregate_function");
}

function isColRef($part){
  return isPartHasType($part, "colref");
}


//input: $part = WHERE[0]
function getCofref($part){
  $parent = $part['no_quotes']['parts'];
  return end($parent);
}

//input: raw[WHERE]


function isTable($part){
  return isPartHasType($part, "table");
}

function hasJoinType($table,$string){
  return $table['join_type'] == $string;
}

function isBracketExpr($part){
  return isPartHasType($part, "bracket_expression");
}

function isSubQuery($part){
  return isPartHasType($part, "subquery");
}

function isOperator($part){
  return isPartHasType($part, "operator");
}

function getOp($part){
  $string = getValue($part);
  $except = array("=", "!=", "LIKE", "NOT", "IN");
  if(in_array($string, $except)) return $string;
  else if($string == "AND")
    return AND_OP;
  else return OR_OP;

}

//input: raw[0] => Table
function getTableName($part){
  return $part['table'];
}

function lastIndex($input){
  return count($input)-1;
}

function isSameCofref($input){
  if(!isColRef($input['0']) || !isColRef($input['2']))
    return false;

  foreach ($input as $key => $value) {
    if(isColRef($value)){
      $ref[$key] = getCofref($value);
      // echo $ref[$key]."\n";
    }
    if(isOperator($value)){
      $op = getOp($value);
      // echo $op;
    }
  }
  // print_r($ref);
  return ($ref['0'] == $ref['2']) && $op == "=";
}

//input raw[SELECT]
function selectToString($input){
  if(isSelectAll($input)) return "";

  $i = -1;
  foreach ($input as $part) {
    $i++;
    $result .= refToString($part,lastIndex($input) == $i);
  }
  return "(".$result.")";
}

//input raw[FROM]
function fromToString($input){
  $i = -1;
  foreach ($input as $part ) {
    $i++;
    if($i > 0){
      $joinType = makeJoinSymbol($part);
      $tables .= $joinType;
    }
    if(isTable($part)){
      $tables .= getTableName($part);
    }
  }
  $result = $tables;
  return $result;
}

// input: raw[WHERE], must to use recur
function whereToString($input){
  if(isSameCofref($input)) return;
  foreach ($input as $key => $value) {
    if(isBracketExpr($value)){
      //sub_tree
      $pilot[$key] = whereToString($value['sub_tree']);
    }
    else if(isSubQuery($value)){
      $pilot[$key] = sqlToString($value['sub_tree']);
    }
    else if(isOperator($value)){
      $pilot[$key] = " ".getOp($value)." ";
      //Convert OP HERE
    }
    else{
      $pilot[$key] = refToString($value,true);
    }

  }

  foreach ($pilot as $value) {
    $result .= $value;
  }
  return "(".$result.")";
}

// input: raw[SELECT]
function makeSelectSymbol($input){
  $result;
  // print(isSelectAll($input));
  if(isSelectAll($input))
    return "";
  else if(isSelectAggregateFunc($input))
    $result = TAU;
  else
    $result = PI;
  //add CSS class to minimize select item here ?
  return $result;
}

function makeJoinSymbol($part){
  if(hasJoinType($part,"RIGHT"))
    return RIGHT_JOIN;
  else if(hasJoinType($part,"LEFT"))
    return LEFT_JOIN;
  else return JOIN;
}

function groupToString($input){
  $i = -1;
  foreach ($input as $part) {
    $i++;
    $result .= refToString($part,lastIndex($input) == $i);
  }
  return $result;
}


// input: raw[SELECT]
function isSelectAll($part){
  return isColRef($part['0']) && getBaseExpr($part['0']) == "*";
}

function isSelectAggregateFunc($part){
  return isAggregateFunc($part[0]);
}

function refToString($part, $isLast){
  if(isColRef($part))
    return colrefToString($part,$isLast);
  else if(isAggregateFunc($part))
    return aggrFuncToString($part,$isLast);
  else
    return getBaseExpr($part);
}

function colrefToString($part, $isLast){
  $result = getBaseExpr($part);
  if(!$isLast) $result .= ", ";
  return $result;
}
// input: $part[expr_type] = aggregate_function;
function aggrFuncToString($part, $isLast){

  if(array_key_exists('sub_tree', $part))
    $paramList = $part['sub_tree'];

  $i = -1;
  foreach ($paramList as $paramArr) {
    $i++;
    $param .= getBaseExpr($paramArr);
    if(!(lastIndex($paramList) == $i))
      $param .= ", ";
  }
  $func = getBaseExpr($part)."(".$param.")";

  $result = $func;
  if(!$isLast)
    $result .= ", ";

  return $result;
}

///////
function sqlToString($raw){
  if(isValidInput($raw)){
    foreach ($raw as $key => $val) {
      if($key == "SELECT")
        $select = makeSelectSymbol($val).selectToString($val);
      else if($key == "FROM")
        $from = "(".fromToString($val).")";
      else if($key == "WHERE"){
        $where = whereToString($val);
        if($where) $where = SIGMA.$where;
      }
      else if($key == "GROUP")
        $group = groupToString($val);
    }
    $result = $group. $select . $where . $from;
  }
  return $result;
}

$parser = new PHPSQLParser($sql);

// echo "Input: ".$sql."\n";
// showResult($parser->parsed);
// print_r($parser->parsed);

$raw = $parser->parsed;


// $select = selectToString($raw['SELECT']);
// $from = fromToString($raw['FROM']);
// $where = whereTostring($raw['WHERE']);

// test($raw,$result, 0);


// echo "select: ". $select . "\n";
// echo "from: ". $from ."\n";
// echo "where: ". $where ."\n";


// print_r($raw);
$result = sqlToString($raw);

echo $result;
echo "\n";




?>
