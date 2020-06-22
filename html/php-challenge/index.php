<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php');
	exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?,reply_post_id=?,created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id']
		));

		header('Location: index.php');
		exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, (SELECT posts.*, likescount FROM posts LEFT JOIN (SELECT post_id, COUNT(post_id) AS likescount FROM likes GROUP BY post_id) AS likestable ON posts.id=likestable.post_id)
p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();
// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}
// RT機能
$retweetMessages = $db->prepare('SELECT post_id FROM poss WHERE member_id=?');
$retweetMessages->execute(array(
	$_SESSION['id']
));
$retweetMessage = $retweetMessages->fetchAll();
//リツイートボタンを押した時
if (isset($_REQUEST['rt'])) {
	$retweet = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$retweet->execute(array($_REQUEST['rt']));
	$retweetTable = $retweet->fetch();
	$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?,retweet_post_id=?, reply_post_id=?,created=NOW()');
	$message->execute(array(
		$member['id'],
		$retweetTable['message'],
		$retweetTable['id'],
		$retweetTable['reply_post_id'],
	));
	//RTの件数表示
	$message = $db->prepare('SELECT retweet_post_id,count(retweet_post_id) AS retweetcount FROM posts WHERE id=? GROUP BY retweet_post_id');
	$message->execute(array(
		$retweetTable['id'],
	));
	$retweetCount = $message->fetch();
	$retweetInsert = $db->prepare('UPDATE posts SET retweetcount=? WHERE id=?');
	$retweetInsert->execute(array(
		$retweetCount['retweetcount'],
		$retweetTable['id'],
	));
	$retweetInsert = $db->prepare('UPDATE posts SET retweetcount=? WHERE retweet_post_id=?');
	$retweetInsert->execute(array(
		$retweetCount['retweetcount'],
		$retweetTable['id'],
	));
	//RTもう一回押すと削除
	$retweetDel = $db->prepare('SELECT member_id,retweet_post_id,retweetcount FROM posts WHERE id=?');
	$retweetDel->execute(array(
		$_REQUEST['rt']
	));
	$retweetDelTable = $retweetDel->fetch();
	if ($retweetDelTable['member_id'] === $member['id'] && $retweetDelTable['retweet_post_id'] > 0) {
		$retweetDelDo = $db->prepare('DELETE FROM posts WHERE id=?');
		$retweetDelDo->execute(array(
			$_REQUEST['rt'],
		));
				//元の投稿のリツイート数も減らす
		$retweetDelCount= $db->prepare('UPDATE posts SET retweetcount=? WHERE id=?');
		$retweetDelCount->execute(array(
			$retweetDelTable['retweetcount']-1,
			$retweetDelTable['retweet_post_id']
		));

		$retweetDelDone = $db->prepare('DELETE FROM posts WHERE retweet_post_id=?');
		$retweetDelDone->execute(array(
			$_REQUEST['rt'],
		));
	
	};

	header('Location: index.php');
	exit();
};

//いいね機能
// その人がいいねを押しているか
$likeMessages = $db->prepare('SELECT post_id FROM likes WHERE member_id=?');
$likeMessages->execute(array(
	$_SESSION['id']
));
$likeMessage = $likeMessages->fetchAll();
//いいねボタンを押した時
if (isset($_REQUEST['like'])) {
	$likesCount = $db->prepare('SELECT count(post_id) as likescount from (SELECT post_id FROM likes WHERE post_id=? AND member_id=? ) AS likepost');
	$likesCount->execute(array(
		$_REQUEST['like'],
		$_SESSION['id']
	));
	$likeCount = $likesCount->fetch();
	//いいね登録
	if ((int)$likeCount['likescount'] === 0) {
		$likeInsert = $db->prepare('INSERT INTO likes(post_id,member_id) VALUES (?,?)');
		$likeInsert->execute(array(
			$_REQUEST['like'],
			$_SESSION['id']
		));
	}
	//いいね削除
	if ((int)$likeCount['likescount'] === 1) {
		$likeDelete = $db->prepare('DELETE FROM likes WHERE post_id=? AND member_id=?');
		$likeDelete->execute(array(
			$_REQUEST['like'],
			$_SESSION['id']
		));

	}
	header('Location: index.php');
	exit();

}
// htmlspecialcharsのショートカット
function h($value)
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>
<!DOCTYPE html>
<html lang="ja">

	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="style.css" />
	</head>

	<body>
	<div id="wrap">
		<div id="head">
			<h1>ひとこと掲示板</h1>
		</div>
		<div id="content">
			<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
			<form action="" method="post">
				<dl>
					<dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
					<dd>
						<textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
						<input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
					</dd>
				</dl>
				<div>
					<p>
						<input type="submit" value="投稿する" />
					</p>
				</div>
			</form>

			<?php
				foreach ($posts as $post) :
					$like = 0;
					for ($i = 0; $i < count($likeMessage); $i++) {
						if ($likeMessage[$i]['post_id'] === $post['id']) {
							$like = $post['id'];
							break;
						}
					}	
			?>
				<div class="msg">
					<?php if ($post['retweet_post_id'] > 0) : ?>
						<p style="font-size:80%; color:blue;"><?php echo h($post['name']); ?>さんがリツイートしました。</p>
					<?Php endif; ?>
					<img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
					<p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]
						<p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a></p>
							<?php
						if ($post['reply_post_id'] > 0) :
						?>
							<a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">
								返信元のメッセージ</a>
						<?php
						endif;
						?>
												<!-- リツイート -->
						<?php if ((int)$post['retweetcount'] === 0) { ?>
							<p class="rt" style="margin-left:50px; margin-top:22px;"><a href="index.php?rt=<?php echo h($post['id']); ?>"><img src="images/retweet.png" alt="" style="width:20px;"></a><?php echo h($post['retweetcount']); ?></p>
						<?php }else{?>
							<p class="afterrt" style="margin-left:50px; margin-top:22px; "><a href="index.php?rt=<?php echo h($post['id']); ?>"><img src="images/after_retweet.png" alt="" style="width:20px;"></a><?php echo h($post['retweetcount']); ?></p>
							<?php };?> 
						<!-- いいね -->
						<?php if ((int)$like === 0){ ?>
							<p class="notlike" style="margin-left:52px; "><a href="index.php?like=<?php 
							//もしリツイートであればリツイート元のidを渡す。
							if($post['retweet_post_id'] > 0){
								echo h($post['retweet_post_id']);
							}else{ 
								echo h($post['id']);}?>"> <img src="images/like.png" alt="" style="width:20px;"></a><?php echo h($post['likescount']) ?></p>
						
						<?php }else{ ?>
							<p class="like" style="margin-left:52px;"><a href="index.php?like=<?php echo h($post['id']); ?>"><img src="images/after_like.png" alt="" style="width:20px;"></a><?php echo h($post['likescount']) ?></p>
						<?Php }; ?>


						<?php
						if ($_SESSION['id'] == $post['member_id']) :
						?>
							[<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
						<?php
						endif;
						?>
					</p>
				</div>
			<?php
			endforeach;
			?>

			<ul class="paging">
				<?php
				if ($page > 1) {
				?>
					<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
				<?php
				} else {
				?>
					<li>前のページへ</li>
				<?php
				}
				?>
				<?php
				if ($page < $maxPage) {
				?>
					<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
				<?php
				} else {
				?>
					<li>次のページへ</li>
				<?php
				}
			
				?>
			</ul>
		</div>
	</div>
	</body>
	</html>