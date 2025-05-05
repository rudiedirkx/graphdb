<?php

$_time = microtime(true);

require 'inc.bootstrap.friends.php';

$app = new FriendsApp($db);

// CREATE PERSON
if (isset($_POST['name'], $_POST['age'], $_POST['hobby'])) {
	$op = @$_POST['_action'] ?: 'save';
	if ($op == 'save') {
		$app->createPerson(array_intersect_key($_POST, array_flip(['name', 'age', 'hobby'])));
	}
	else {
		$app->deletePerson($_POST['name']);
	}

	return do_redirect('');
}

// CREATE FRIENDSHIP
if (isset($_POST['person1'], $_POST['person2'])) {
	$app->createFriendship($_POST['person1'], $_POST['person2']);

	return do_redirect('');
}

// DELETE FRIENDSHIP
if (isset($_GET['deletefriendship'])) {
	$app->deleteFriendship($_GET['deletefriendship']);

	return do_redirect('');
}

// header('Content-type: text/plain; charset=utf-8');
// print_r($app->findFriendshipPath('Erwin', 'Rudie'));
// exit;

$people = $app->getAllPeopleOptions();
$friendships = $app->getAllFriendships();

// FIND FRIENDSHIP
$friendshipPath = null;
if (!empty($_GET['friend1']) && !empty($_GET['friend2'])) {
	$friendshipPath = $app->findFriendshipPath($_GET['friend1'], $_GET['friend2']);
	// dd($friendshipPath);
}

?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta charset="utf-8" />
<title>Graph Friends</title>
<style>
button:default {
	font-weight: bold;
}
button.delete {
	color: #b00;
}
</style>

<h2>Friendships</h2>

<table class="friendships" border="1" cellspacing="0" cellpadding="5">
	<thead>
		<tr>
			<th>Friend 1</th>
			<th>Friend 2</th>
			<th>Since</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<? foreach ($friendships as $rel): ?>
			<tr>
				<td><?= html($rel['name1']) ?></td>
				<td><?= html($rel['name2']) ?></td>
				<td><?= $rel['since'] ? date('Y-m-d H:i:s', $rel['since']) : '' ?></td>
				<td><a href="?deletefriendship=<?= html($rel['fid']) ?>">delete</a></td>
			</tr>
		<? endforeach ?>
	</tbody>
</table>

<h2>Find * friendship</h2>

<form class="find-path" method="get" action>
	<p>
		How is
		<select name="friend1"><?= html_options(['' => '-- Person'] + $people, @$_GET['friend1']) ?></select>
		friends with
		<select name="friend2"><?= html_options(['' => '-- Person'] + $people, @$_GET['friend2']) ?></select>
		?
	</p>
	<?if ($friendshipPath): ?>
		<p style="color: green"><?= html(implode(' > ', array_map(function($person) {
			return $person['name'];
		}, $friendshipPath->friends))) ?></p>
	<? endif ?>
	<p><button>Find friendship path</button></p>
</form>

<h2>Create friendship</h2>

<form class="create-friendship" method="post" action>
	<p>
		Make
		<select name="person1"><?= html_options(['' => '-- Person'] + $people) ?></select>
		friends with
		<select name="person2"><?= html_options(['' => '-- Person'] + $people) ?></select>
	</p>
	<p><button>Friend them</button></p>
</form>

<h2>Create/update/delete person</h2>

<form class="create-person" method="post" action>
	<p>Name: <input name="name" /></p>
	<p>Age: <input name="age" /></p>
	<p>Hobby: <input name="hobby" /></p>
	<p>
		<button name="_action" value="save">Save person</button>
		<button name="_action" value="delete" class="delete">Delete person</button>
	</p>
</form>

<pre><?= round(1000 * (microtime(true) - $_time)) ?> ms</pre>
<pre></pre>

<script>
window.onload = function() {
	document.querySelector('pre+pre').textContent = (performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart) + " ms";
};

document.querySelector('.create-friendship select').onchange = function() {
	document.querySelector('[name="name"]').value = this.value;
};
</script>

<details>
	<summary>$friendships</summary>
	<pre><?php print_r($friendships); ?></pre>
</details>

<details>
	<summary>$people</summary>
	<pre><?php print_r($app->getAllPeople()); ?></pre>
</details>

<details>
	<summary>$friendshipPath</summary>
	<pre><?php print_r($friendshipPath); ?></pre>
</details>

<details>
	<summary>Queries</summary>
	<pre><?php print_r($app->getQueries()); ?></pre>
</details>
