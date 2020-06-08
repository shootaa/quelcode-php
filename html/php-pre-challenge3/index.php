<?php
try{
    //データベース接続
    $dsn = 'mysql:dbname=test;host=mysql';
    $dbuser = 'test';
    $dbpassword = 'test';
    $dbh=new PDO($dsn,$dbuser,$dbpassword);
    //SQL実行小さい順
    $sql = 'SELECT value FROM prechallenge3 ORDER BY value  ASC';
    $stmt=$dbh->query($sql);
    $result=$stmt->fetchall(PDO::FETCH_ASSOC);
    //結果を配列に変換
    $dbArray = array_column($result,'value');    
    $limit = $_GET['target'];
    $limitInt =(int)$limit;
    //リクエストが不正の場合の処理
    if($limit<1|!ctype_digit($limit)){
        http_response_code( 400 );
        echo'Bad Request';
        exit();
    }
    //動的計画法。何通りかを求める。
    function Dp($dbArray){
        $limit = $_GET['target'];
        $limitInt =(int)$limit;
        for($i=0;$i<=count($dbArray);$i++){
            for($j=0;$j<=$limitInt;$j++){
                $array[$i][$j]=0;
            }
        }
        $array[0][0]=1;
        for($i=1;$i<=count($dbArray);$i++){
            for($j=$limitInt;$j>=0;$j--){
                $m=$dbArray[$i-1];
                //２週目以降に値を保持する
                if($i>=1){
                    $array[$i][$j]= $array[$i-1][$j];
                }
                if($array[$i][$j]!==0 && $j+$m<=$limitInt){
                    $array[$i][$j+$m]=$array[$i-1][$j]+$array[$i-1][$j+$m];
                }
            }
        }
        return $array;
    }
    $dp=Dp($dbArray);
    //動的計画法から復元する。
    function reverse($dbArray,$dp,$i,$j,$k,$tmp,&$answer){
        $limit = $_GET['target'];
        $limitInt =(int)$limit;
        $countArray=count($dbArray);
        $m=$dbArray[$k];
        if($i>0 && $k>=0){
            //上に遡る
            if($dp[$i-1][$j]>0){
                reverse($dbArray,$dp,$i-1,$j,$k-1,$tmp,$answer);
            }
            //左斜め上に遡る
            if($limitInt-$m>=0 && $dp[$i-1][$j-$m]>0){
                array_push($tmp,$m);
                reverse($dbArray,$dp,$i-1,$j-$m,$k-1,$tmp,$answer);
            }
        }
        if($i===0 && $j===0 && !empty($tmp)){
            array_push($answer,$tmp);
        }
        return $answer;
    }
    $tmp=[];
    $answer=[];
    //JSON形式に変換する。
    $solve=json_encode((reverse($dbArray,$dp,count($dbArray),$limitInt,count($dbArray)-1,$tmp,$answer)));
    print_r($solve);
}catch(PDOException $e){
    echo'接続エラー'.$e->getMessage();
}


