<?php

use rdx\graphdb\Database;
use rdx\graphdb\Query;

$_time = microtime(1);

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
		<?= html(implode(' > ', array_map(function($person) {
			return $person['name'];
		}, $friendshipPath->friends))) ?>
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

<pre><?= round(1000 * (microtime(1) - $_time)) ?> ms</pre>
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
<?php

class FriendsApp {

	protected $db;
	protected $cache = [];

	public function __construct(Database $db) {
		$this->db = $db;

		// CREATE CONSTRAINT ON (p:Person) ASSERT p.name IS UNIQUE
	}

	protected function cache($name, callable $worker) {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	public function findFriendshipPath($person1, $person2) {
		return $this->db->one('
			MATCH path=(e:Person {name: {person1}})-[f:IS_FRIENDS_WITH*]-(j:Person {name: {person2}})
			RETURN nodes(path) AS friends, size(f) AS length
			ORDER BY length ASC
			LIMIT 1
		', compact('person1', 'person2'));
	}

	public function deleteFriendship($id) {
		return $this->db->execute(Query::make()
			->match('()-[f:IS_FRIENDS_WITH]->()')
			->where('id(f) = {id}', ['id' => (int) $id])
			->delete('f')
		);
	}

	public function createFriendship($person1, $person2) {
		$data = ['since' => time()];
		return $this->db->execute(Query::make()
			->match('(p1:Person)')->match('(p2:Person)')
			->where('p1.name = {person1}', compact('person1'))
			->where('p2.name = {person2}', compact('person2'))
			->create('(p1)-[:IS_FRIENDS_WITH {data}]->(p2)', compact('data'))
		);
	}

	public function deletePerson($name) {
		return $this->db->execute(Query::make()
			->match('(p:Person)')
			->where('p.name = {name}', compact('name'))
			->delete('p')
		);
	}

	public function createPerson(array $data) {
		$data = array_map('trim', $data);

		// Person.name is UNIQUE, so this is a very easy MERGE/REPLACE/UPSERT
		return $this->db->execute(Query::make()
			->merge('(p:Person {name: {name}})', ['name' => $data['name']])
			->set('p += {data}', ['data' => $data])
		);
	}

	public function getAllFriendships() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(p1)-[f:IS_FRIENDS_WITH]->(p2)')
				->return('p1.name AS name1', 'p2.name AS name2', 'f.since AS since', 'id(f) AS fid')
				->order('name1 ASC', 'name2 ASC')
			);
		});
	}

	public function getAllPeople() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(p:Person)')
				->return('p')
				->order('p.name ASC')
			);
		});
	}

	public function getAllPeopleOptions() {
		return $this->cache(__FUNCTION__, function() {
			$people = $this->getAllPeople();

			$options = [];
			foreach ($people as $person) {
				$props = array_filter([$person->p['age'], $person->p['hobby']]);
				$options[ $person->p['name'] ] = $person->p['name'] . ($props ? ' (' . implode(', ', $props) . ')' : '');
			}

			return $options;
		});
	}

}
