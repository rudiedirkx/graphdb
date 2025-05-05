<?php

// Movie has Characters
// Character has Person
// Person-Movie only through Character

$_time = microtime(1);

require 'inc.bootstrap.imdb.php';

$app = new ImdbApp($db);

// MOVIE
if ( isset($_POST['id'], $_POST['title'], $_POST['year']) ) {
	// ini_set('html_errors', 0);
	// header('Content-type: text/plain; charset=utf-8');

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
	// ini_set('html_errors', 0);
	// header('Content-type: text/plain; charset=utf-8');

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
	// ini_set('html_errors', 0);
	// header('Content-type: text/plain; charset=utf-8');

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

$moviesOptions = $app->getAllMoviesOptions();
$characterOptions = $app->getAllCharactersOptions();
$peopleOptions = $app->getAllPeopleOptions();

$roles = $app->getAllRoles();
// dd($roles);

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
		<th>Fee</th>
	</tr>
	<? foreach ($roles as $role): ?>
		<tr>
			<td><?= $role->m['title'] ?> (<?= $role->m['year'] ?>)</td>
			<td><?= $role->p['name'] ?></td>
			<td><a href="?character=<?= $role->c['uuid'] ?>"><?= $role->c['name'] ?></a></td>
			<td><?= $role['fee'] ?></td>
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
