<?php

use rdx\graphdb\Database;
use rdx\graphdb\Query;

$_time = microtime(1);

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
// print_r($hierarchy);

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
				<td><a href="?deletetweet=<?= html($tweet->tweet->id()) ?>">delete</a></td>
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

<pre><?= round(1000 * (microtime(1) - $_time)) ?> ms</pre>
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
<?php

class TwitterApp {

	protected $db;
	protected $cache = [];

	public function __construct(Database $db) {
		$this->db = $db;
	}

	protected function cache($name, callable $worker) {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	public function getTweets() {
		return $this->cache(__FUNCTION__, function() {
			return array_reduce($this->db->many('
				MATCH (t:Tweet)-[:AUTHORED_BY]->(u:User)
				OPTIONAL MATCH (t)-[:REPLIES_TO]->(p:Tweet)
				RETURN t AS tweet, u AS author, id(p) AS pid
				ORDER BY t.created ASC
			'), function($tweets, $tweet) {
				return $tweets + [$tweet->tweet->id() => $tweet];
			}, []);
		});
	}

	public function makeTweetHierarchy(array $tweets) {
		// Create hierarchy with automatic references
		$parents = [];
		foreach ($tweets as $tid => $tweet) {
			if ($pid = $tweet['pid']) {
				$tweets[$pid]->replies[] = $tweet;
			}
			else {
				$parents[] = $tweet;
			}
		}

		// Flatten & remove hierarchy
		$ordered = [];
		$add = function($level, $tweets) use (&$add, &$ordered) {
			foreach ($tweets as $tweet) {
				$ordered[] = [$level, $tweet];

				if (isset($tweet->replies)) {
					$add($level + 1, $tweet->replies);
					unset($tweet->replies);
				}
			}
		};
		$add(0, $parents);

		return $ordered;
	}

	public function getTweetOptions() {
		return $this->cache(__FUNCTION__, function() {
			return array_reduce($this->getTweets(), function($options, $tweet) {
				return $options + [$tweet->tweet->id() => $tweet->author['name'] . ': ' . $tweet->tweet['text']];
			}, []);
		});
	}

	public function createTweet($author, $parent, $text) {
		$tweet = ['text' => $text, 'created' => time()];
		$query = Query::make()
			->match('(u:User {name: {author}})', compact('author'))
			->create('(t:Tweet {tweet})', compact('tweet'))
			->create('(t)-[:AUTHORED_BY]->(u)');

		if ($parent !== '') {
			$query
				->match('(p:Tweet)')
				->where('id(p) = {pid}', ['pid' => (int) $parent])
				->create('(t)-[:REPLIES_TO]->(p)');
		}

		return $this->db->execute($query);
	}

	public function deleteTweet($id) {
		return $this->db->execute(Query::make()
			->match('(t:Tweet)')
			->where('id(t) = {tid}', ['tid' => (int) $id])
			->detachDelete('t')
		);
	}

	public function createUser(array $data) {
		$data = array_map('trim', $data);

		// User.name is UNIQUE, so this is a very easy MERGE/REPLACE/UPSERT
		return $this->db->execute(Query::make()
			->merge('(u:User {name: {name}})', ['name' => $data['name']])
			// ->set('u += {data}', ['data' => $data])
		);
	}

	public function getUsers() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many('
				MATCH (u:User)
				RETURN u AS user
				ORDER BY u.name
			');
		});
	}

	public function getUserOptions() {
		return $this->cache(__FUNCTION__, function() {
			return array_reduce($this->getUsers(), function($options, $user) {
				return $options + [$user->user['name'] => $user->user['name']];
			}, []);
		});
	}

}
