<?php

use rdx\graphdb\GraphDatabase;

require 'inc.bootstrap.php';

$app = new FriendsApp($db);

// CREATE PERSON
if (isset($_POST['name'], $_POST['age'], $_POST['hobby'])) {
	$app->createPerson(array_intersect_key($_POST, array_flip(['name', 'age', 'hobby'])));

	return do_redirect('');
}

// CREATE FRIENDSHIP
if (isset($_POST['friend1'], $_POST['friend2'])) {
	$app->createFriendship($_POST['friend1'], $_POST['friend2']);

	return do_redirect('');
}

// DELETE FRIENDSHIP
if (isset($_GET['deletefriendship'])) {
	$app->deleteFriendship($_GET['deletefriendship']);

	return do_redirect('');
}

$people = $app->getAllPeopleOptions();
$friends = $app->getAllFriends();

// header('Content-type: text/plain; charset=utf-8');
// print_r($friends);

?>

<h2>Friendships</h2>

<table border="1" cellspacing="0" cellpadding="5">
	<thead>
		<tr>
			<th>Friend 1</th>
			<th>Friend 2</th>
		</tr>
	</thead>
	<tbody>
		<? foreach ($friends as $rel): ?>
			<tr>
				<td><?= html($rel->p1['name']) ?></td>
				<td><?= html($rel->p2['name']) ?></td>
				<td><a href="?deletefriendship=<?= html($rel['fid']) ?>">delete</a></td>
			</tr>
		<? endforeach ?>
	</tbody>
</table>

<h2>Create friendship</h2>

<form method="post" action>
	<p>Friend 1: <select name="friend1"><?= html_options(['' => '--'] + $people) ?></select></p>
	<p>Friend 2: <select name="friend2"><?= html_options(['' => '--'] + $people) ?></select></p>
	<p><button>Friend them</button></p>
</form>

<h2>Create person</h2>

<form method="post" action>
	<p>Name: <input name="name" /></p>
	<p>Age: <input name="age" /></p>
	<p>Hobby: <input name="hobby" /></p>
	<p><button>Save person</button></p>
</form>

<?php

class FriendsApp {

	protected $db;
	protected $cache = [];

	public function __construct(GraphDatabase $db) {
		$this->db = $db;

		// CREATE CONSTRAINT ON (p:Person) ASSERT p.name IS UNIQUE
	}

	protected function cache($name, callable $worker) {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	public function deleteFriendship(int $id) {
		return $this->db->execute("
			MATCH ()-[f:IS_FRIENDS_WITH]->()
			WHERE id(f) = $id
			DELETE f
		");
	}

	public function createFriendship($person1, $person2) {
		return $this->db->execute('
			MATCH (p1:Person), (p2:Person)
			WHERE p1.name = {person1} AND p2.name = {person2}
			CREATE (p1)-[:IS_FRIENDS_WITH]->(p2)
		', compact('person1', 'person2'));
	}

	public function createPerson(array $data) {
		// Person.name is UNIQUE, so this is a very easy MERGE/REPLACE/UPSERT
		return $this->db->execute('
			MERGE (p:Person {name: {name}})
			SET p+= {data}
		', ['name' => $data['name'], 'data' => $data]);
	}

	public function getAllFriends() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many('
				MATCH (p1)-[f:IS_FRIENDS_WITH]->(p2)
				RETURN id(p1) AS id1, p1, id(p2) AS id2, p2, id(f) AS fid, f
				ORDER BY p1.name ASC, p2.name ASC
			');
		});
	}

	public function getAllPeople() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many('
				MATCH (p:Person)
				RETURN p.name AS id, p
				ORDER BY p.name ASC
			');
		});
	}

	public function getAllPeopleOptions() {
		return $this->cache(__FUNCTION__, function() {
			$people = $this->getAllPeople();

			$options = [];
			foreach ($people as $person) {
				$options[ $person['id'] ] = rtrim($person->p['name'] . ' (' . $person->p['age'] . ', ' . $person->p['hobby'] . ')', ' ,)(');
			}

			return $options;
		});
	}

}
