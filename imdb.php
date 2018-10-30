<?php

// Movie has Characters
// Character has Person
// Person-Movie only through Character

use rdx\graphdb\Container;
use rdx\graphdb\Database;
use rdx\graphdb\Query;

$_time = microtime(1);

require 'inc.bootstrap.imdb.php';

$app = new ImdbApp($db);

// MOVIE
if ( isset($_POST['id'], $_POST['title'], $_POST['year']) ) {
	header('Content-type: text/plain; charset=utf-8');

	$op = @$_POST['_action'] ?: 'save';
	if ($op == 'save') {
		$app->saveMovie($_POST['id'], array_intersect_key($_POST, array_flip(['title', 'year'])));
	}
	else {
		$app->deleteMovie($_POST['id']);
	}

	return do_redirect('');
}

// PERSON
if ( isset($_POST['id'], $_POST['name'], $_POST['nationality']) ) {
	header('Content-type: text/plain; charset=utf-8');

	$op = @$_POST['_action'] ?: 'save';
	if ($op == 'save') {
		$app->savePerson($_POST['id'], array_intersect_key($_POST, array_flip(['name', 'nationality'])));
	}
	else {
		$app->deletePerson($_POST['id']);
	}

	return do_redirect('');
}

// CHARACTER
if ( isset($_POST['movie_id'], $_POST['person_id'], $_POST['character_id'], $_POST['character']) ) {
	header('Content-type: text/plain; charset=utf-8');

	if ( $_POST['character'] ) {
		$app->createCharacter($_POST['movie_id'], $_POST['person_id'], $_POST['character']);
	}
	else {
		$app->createRole($_POST['movie_id'], $_POST['person_id'], $_POST['character_id']);
	}

	return do_redirect('');
}

// header('Content-type: text/plain; charset=utf-8');
// print_r($app->getAllPeopleOptions());
// print_r($app->getAllMoviesOptions());
// print_r($app->getAllCharactersOptions());
// print_r($app->getAllRoles());
// exit;

$characterOptions = $app->getAllCharactersOptions();
$peopleOptions = $app->getAllPeopleOptions();
$moviesOptions = $app->getAllMoviesOptions();

$roles = $app->getAllRoles();

$character = empty($_GET['character']) ? null : $app->getCharacter($_GET['character']);
$characterAppearances = empty($character) ? null : $app->getCharacterAppearances($character);

?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta charset="utf-8" />
<title>Graph Movies</title>
<style>
button:default {
	font-weight: bold;
}
button.delete {
	color: #b00;
}
</style>

<? if ($character): ?>
	<h2>Character <em><?= $character['name'] ?></em> appears in</h2>

	<table border="1" cellpadding="5" cellspacing="0">
		<tr>
			<th>Movie</th>
			<th>Person</th>
		</tr>
		<? foreach ($characterAppearances as $appearance): ?>
			<tr>
				<td><?= $appearance->m['title'] ?> (<?= $appearance->m['year'] ?>)</td>
				<td><?= $appearance->p['name'] ?></td>
			</tr>
		<? endforeach ?>
	</table>
<? endif ?>

<h2>All roles</h2>

<table border="1" cellpadding="5" cellspacing="0">
	<tr>
		<th>Movie</th>
		<th>Person</th>
		<th>Character</th>
	</tr>
	<? foreach ($roles as $role): ?>
		<tr>
			<td><?= $role->m['title'] ?> (<?= $role->m['year'] ?>)</td>
			<td><?= $role->p['name'] ?></td>
			<td><a href="?character=<?= $role->c['uuid'] ?>"><?= $role->c['name'] ?></a></td>
		</tr>
	<? endforeach ?>
</table>

<h2>Create role</h2>

<form method="post" action>
	<p>Movie: <select name="movie_id"><?= html_options($moviesOptions) ?></select></p>
	<p>Person: <select name="person_id"><?= html_options($peopleOptions) ?></select></p>
	<p>
		Character:
		<select name="character_id"><?= html_options($characterOptions, null, '-- New') ?></select>
		or
		<input name="character" />
	</p>
	<p>
		<button name="_action" value="save">Create character</button>
	</p>
</form>

<h2>Create/update/delete person</h2>

<form method="post" action>
	<p>Update: <select name="id" data-type="people"><?= html_options($peopleOptions, null, '-- New') ?></select></p>
	<p>Name: <input name="name" required /></p>
	<p>Nationality: <input name="nationality" required /></p>
	<p>
		<button name="_action" value="save">Save person</button>
		<button name="_action" value="delete" class="delete">Delete person</button>
	</p>
</form>

<h2>Create/update/delete movie</h2>

<form method="post" action>
	<p>Update: <select name="id" data-type="movies"><?= html_options($moviesOptions, null, '-- New') ?></select></p>
	<p>Title: <input name="title" required /></p>
	<p>Year: <input name="year" type="number" value="2000" required /></p>
	<p>
		<button name="_action" value="save">Save movie</button>
		<!-- <button name="_action" value="delete" class="delete">Delete movie</button> -->
	</p>
</form>

<pre><?= round(1000 * (microtime(1) - $_time)) ?> ms</pre>

<script>
const data = {
	people: <?= json_encode($app->serializeContainers($app->getAllPeople())) ?>,
	movies: <?= json_encode($app->serializeContainers($app->getAllMovies())) ?>,
};

[].forEach.call(document.querySelectorAll('select[data-type]'), function(select) {
	select.onchange = function(e) {
		if (this.value) {
			Array.from(Object.entries(data[this.dataset.type][this.value])).forEach(([key, value]) => {
				this.form.elements[key] && (this.form.elements[key].value = value);
			});
		}
	};
});
</script>

<details>
	<summary>$people</summary>
	<pre><?php print_r($app->getAllPeople()) ?></pre>
</details>

<details>
	<summary>$movies</summary>
	<pre><?php print_r($app->getAllMovies()) ?></pre>
</details>

<details>
	<summary>$characters</summary>
	<pre><?php print_r($app->getAllCharacters()) ?></pre>
</details>

<details>
	<summary>Queries (<?= count($queries = $app->getQueries()) ?>)</summary>
	<pre><?php print_r($queries) ?></pre>
</details>
<?php

class ImdbApp {

	protected $db;
	protected $cache = [];

	public function __construct(Database $db) {
		$this->db = $db;

		$db->middleware('log', function(callable $next, $query, array $params) {
			$_SESSION['queries'][] = [
				'query' => "\n" . trim($query, "\r\n"),
				'params' => $params,
			];
			return $next($query, $params);
		}, 1000);

		// CREATE INDEX ON :Person (uuid)
		// CREATE INDEX ON :Movie (uuid)
		// CREATE INDEX ON :Character (uuid)
	}

	protected function cache($name, callable $worker) {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	public function serializeContainers(array $containers) {
		$objects = [];
		foreach ($containers as $container) {
			$objects[ $container['uuid'] ] = $container;
		}
		return $objects ?: new stdClass;
	}

	public function getAllPeople() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->manyNode(Query::make()
				->match('(x:Person)')
				->return('x')
				->order('x.name')
			);
		});
	}

	public function getAllPeopleOptions() {
		return $this->cache(__FUNCTION__, function() {
			$people = $this->getAllPeople();

			$options = [];
			foreach ($people as $person) {
				$label = $person['name'] . ' (' . $person['nationality'] . ')';
				$options[ $person['uuid'] ] = $label;
			}

			return $options;
		});
	}

	public function getAllMovies() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->manyNode(Query::make()
				->match('(x:Movie)')
				->return('x')
				->order('x.year, x.title')
			);
		});
	}

	public function getAllMoviesOptions() {
		return $this->cache(__FUNCTION__, function() {
			$movies = $this->getAllMovies();

			$options = [];
			foreach ($movies as $movie) {
				$label = $movie['title'] . ' (' . $movie['year'] . ')';
				$options[ $movie['uuid'] ] = $label;
			}

			return $options;
		});
	}

	public function getAllCharacters() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(c:Character)-[:HAS_ROLE]->(r:Role)<-[:HAS_ROLE]-(m:Movie)')
				->return('c, collect(distinct m.title)[..2] AS movies')
				->order('c.name')
			);
		});
	}

	public function getAllCharactersOptions() {
		return $this->cache(__FUNCTION__, function() {
			$characters = $this->getAllCharacters();

			$options = [];
			foreach ($characters as $character) {
				$options[ $character->c['uuid'] ] = $character->c['name'] . ' (' . implode(', ', $character['movies']) . ')';
			}

			return $options;
		});
	}

	public function createCharacter($movieUuid, $personUuid, $name) {
		return $this->db->execute(Query::make()
			->match('(p:Person {uuid: {p}})', ['p' => $personUuid])
			->match('(m:Movie {uuid: {m}})', ['m' => $movieUuid])
			->create('(c:Character {name: {name}, uuid: {uuid}})', [
				'name' => $name,
				'uuid' => $this->db->makeUuid(),
			])
			->create('(x:Role)')
			->create('(p)-[:HAS_ROLE]->(x)')
			->create('(c)-[:HAS_ROLE]->(x)')
			->create('(m)-[:HAS_ROLE]->(x)')
		);
	}

	public function createRole($movieUuid, $personUuid, $characterUuid) {
		return $this->db->execute(Query::make()
			->match('(p:Person {uuid: {p}})', ['p' => $personUuid])
			->match('(m:Movie {uuid: {m}})', ['m' => $movieUuid])
			->match('(c:Character {uuid: {c}})', ['c' => $characterUuid])
			->create('(x:Role)')
			->create('(p)-[:HAS_ROLE]->(x)')
			->create('(c)-[:HAS_ROLE]->(x)')
			->create('(m)-[:HAS_ROLE]->(x)')
		);
	}

	public function getAllRoles() {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(n:Role)<-[:HAS_ROLE]-(c:Character)')
				->match('(n)<-[:HAS_ROLE]-(m:Movie)')
				->match('(n)<-[:HAS_ROLE]-(p:Person)')
				->return('p, c, m')
				->order('m.year, m.title, p.name')
			);
		});
	}

	public function getCharacter($uuid) {
		return $this->db->oneNode(Query::make()
			->match('(c:Character {uuid: {uuid}})', ['uuid' => $uuid])
			->return('c')
		);
	}

	public function getCharacterAppearances(Container $character) {
		return $this->db->many(Query::make()
			->match('(c:Character {uuid: {uuid}})-[:HAS_ROLE]->(r:Role)<-[:HAS_ROLE]-(p:Person)', ['uuid' => $character['uuid']])
			->match('(r)<-[:HAS_ROLE]-(m:Movie)')
			->return('p, m')
		);
	}

	public function savePerson($uuid = null, array $data) {
		$data = array_map('trim', $data);

		return $this->db->merge('Person', $uuid, $data);
	}

	public function deletePerson($uuid) {
		return $this->db->delete('Person', $uuid);
	}

	public function saveMovie($uuid = null, array $data) {
		$data = array_map('trim', $data);

		return $this->db->merge('Movie', $uuid, $data);
	}

	public function deleteMovie($uuid) {
		return $this->db->delete('Movie', $uuid);
	}

	public function getQueries() {
		$sessionQueries = $_SESSION['queries'] ?? [];
		$_SESSION['queries'] = [];
		return $sessionQueries;
	}

}
