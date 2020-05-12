<?php
$array = explode(',', $_GET['array']);//Getで受け取った配列を変数に格納する
echo'ソート前';
echo "<pre>";
print_r($array);
echo "</pre>";

 /**
  * クイックソートする
  *@param 配列 $array
  *@return 配列 $result
  */
function quicksort($array) {
    if (count($array) <= 1) {
        return $array;
    }
    $pivot = array_shift($array); // 先頭をピボットとする
    $left = $right = [];//左右の配列の準備をする
    foreach ($array as $value) {
        if ($value < $pivot) {
            $left[]  = $value;// ピボットより小さい数を左の配列に格納する
        } else {
            $right[] = $value;  // ピボットより大きい数を右の配列に格納する
        }
    }
    $result= array_merge(quicksort($left), array($pivot), quicksort($right));// 再帰処理
    return $result;
}
echo'ソート後';
echo "<pre>";
print_r(quicksort($array));
echo "</pre>";
// // 修正はここから
// // for ($i = 0; $i < count($array); $i++) {
// // // 修正はここまで
?>
