<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
	$id = $_REQUEST['id'];
	
	// 投稿を検査する
	$messages = $db->prepare('SELECT * FROM posts WHERE id=?');
	$messages->execute(array($id));
	$message = $messages->fetch();
	$originMessages = $db->prepare('SELECT * FROM posts WHERE retweet_post_id=?');
	$originMessages->execute(array($id));
	$originMessage = $originMessages->fetch();
	if ($message['member_id'] == $_SESSION['id']) {
		// 削除する
		$del = $db->prepare('DELETE FROM posts WHERE id=?');
		$del->execute(array($id));
		if($originMessage['id']){
			//その投稿がリツイートのオリジナルの場合
			$retweetDelete =$db->prepare('DELETE FROM posts WHERE retweet_post_id=?');
			$retweetDelete->execute(array(
				$message['id']
			));
		}
	}
}

header('Location: index.php'); exit();
