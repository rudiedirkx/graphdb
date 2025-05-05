<?php

$_time = microtime(true);

require 'inc.bootstrap.twitter.php';

$app = new TwitterApp($db);

// CREATE USER
if (isset($_POST['name'])) {
	$op = @$_POST['_action'] ?: 'save';
	if ($op == 'save') {
		$app->createUser(['name' => $_POST['name']]);
	}
	else {
		$app->deleteUser($_POST['name']);
	}

	return do_redirect('');
}

// CREATE TWEET
if (isset($_POST['author'], $_POST['parent'], $_POST['text'])) {
	$app->createTweet($_POST['author'], $_POST['parent'], $_POST['text']);

	return do_redirect('');
}

// DELETE TWEET
if (isset($_GET['deletetweet'])) {
	$app->deletetweet($_GET['deletetweet']);

	return do_redirect('');
}

// header('Content-type: text/plain; charset=utf-8');

$users = $app->getUsers();
$userOptions = $app->getUserOptions();
$tweets = $app->getTweets();
$tweetOptions = $app->getTweetOptions();

$hierarchy = $app->makeTweetHierarchy($tweets);
// dump($hierarchy);

?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta charset="utf-8" />
<title>Graph Twitter</title>
<style>
.level-1 .text {
	padding-left: 1em;
}
.level-2 .text {
	padding-left: 2em;
}
.level-3 .text {
	padding-left: 3em;
}
.level-4 .text {
	padding-left: 4em;
}
button:default {
	font-weight: bold;
}
button.delete {
	color: #b00;
}
</style>

<h2>Tweets</h2>

<table class="friendships" border="1" cellspacing="0" cellpadding="5">
	<thead>
		<tr>
			<th>Author</th>
			<th>Text</th>
			<th>Posted on</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<? foreach ($hierarchy as $info):
			list($level, $tweet) = $info;
			?>
			<tr class="level-<?= $level ?>">
				<td><?= html($tweet->author['name']) ?></td>
				<td class="text"><?= html($tweet->tweet['text']) ?></td>
				<td><?= date('Y-m-d H:i:s', $tweet->tweet['created']) ?></td>
				<td><a href="?deletetweet=<?= html($tweet->tweet['uuid']) ?>">delete</a></td>
			</tr>
		<? endforeach ?>
	</tbody>
</table>

<h2>Create tweet</h2>

<form class="create-tweet" method="post" action>
	<p>Author: <select name="author"><?= html_options(['' => '-- User'] + $userOptions) ?></select></p>
	<p>Reply to: <select name="parent"><?= html_options(['' => '-- Tweet'] + $tweetOptions) ?></select></p>
	<p>Text: <input name="text" /></p>
	<p><button>Create tweet</button></p>
</form>

<h2>Users</h2>

<ul>
	<? foreach ($users as $user): ?>
		<li><?= html($user->user['name']) ?></li>
	<? endforeach ?>
</ul>

<h2>Create user</h2>

<form class="create-user" method="post" action>
	<p>Name: <input name="name" /></p>
	<p>
		<button name="_action" value="save">Save user</button>
		<button name="_action" value="delete" class="delete">Delete user</button>
	</p>
</form>

<pre><?= round(1000 * (microtime(true) - $_time)) ?> ms</pre>
<pre></pre>

<script>
window.onload = function() {
	document.querySelector('pre+pre').textContent = (performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart) + " ms";
};
</script>

<details>
	<summary>$tweets</summary>
	<pre><?php print_r($tweets); ?></pre>
</details>

<details>
	<summary>$users</summary>
	<pre><?php print_r($users); ?></pre>
</details>

<details>
	<summary>Queries</summary>
	<pre><?php print_r($app->getQueries()); ?></pre>
</details>
