<?php

use rdx\graphdb\Container;
use rdx\graphdb\Database;
use rdx\graphdb\Query;

class ImdbApp {

	/** @var AssocArray */
	protected array $cache = [];

	public function __construct(
		protected Database $db,
	) {
		$db->middleware('log', function(Closure $next, $query, array $params) {
			// @phpstan-ignore offsetAccess.nonOffsetAccessible
			$_SESSION['queries'][] = [
				'query' => "\n" . trim($query, "\r\n"),
				'params' => $params,
			];
			return $next($query, $params);
		}, 1000);

		// CREATE INDEX person_uuid FOR (x:Person) ON (x.uuid)
		// CREATE INDEX movie_uuid FOR (x:Movie) ON (x.uuid)
		// CREATE INDEX character_uuid FOR (x:Character) ON (x.uuid)
	}

	protected function cache(string $name, Closure $worker) : mixed {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	/**
	 * @param list<Container> $containers
	 * @return array<string, object>
	 */
	public function serializeContainers(array $containers) : array {
		$objects = [];
		foreach ($containers as $container) {
			$objects[ $container['uuid'] ] = $container;
		}
		return $objects ?: new stdClass;
	}

	/**
	 * @return list<Container>
	 */
	public function getAllPeople() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->manyNode(Query::make()
				->match('(x:Person)')
				->return('x')
				->order('x.name')
			);
		});
	}

	/**
	 * @return list<string>
	 */
	public function getAllPeopleOptions() : array {
		return $this->cache(__FUNCTION__, function() {
			$people = $this->getAllPeople();

			$options = [];
			foreach ($people as $person) {
				$label = sprintf('%s (%s)', $person['name'], $person['nationality']);
				$options[ $person['uuid'] ] = $label;
			}

			return $options;
		});
	}

	/**
	 * @return list<Container>
	 */
	public function getAllMovies() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->manyNode(Query::make()
				->match('(x:Movie)')
				->return('x')
				->order('x.title, x.year')
			);
		});
	}

	/**
	 * @return list<string>
	 */
	public function getAllMoviesOptions() : array {
		return $this->cache(__FUNCTION__, function() {
			$movies = $this->getAllMovies();

			$options = [];
			foreach ($movies as $movie) {
				$label = sprintf('%s (%s)', $movie['title'], $movie['year']);
				$options[ $movie['uuid'] ] = $label;
			}

			return $options;
		});
	}

	/**
	 * @return list<Container>
	 */
	public function getAllCharacters() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(c:Character)-[:HAS_ROLE]->(r:Role)<-[:HAS_ROLE]-(m:Movie)')
				->return('c, collect(distinct m.title)[..2] AS movies')
				->order('c.name')
			);
		});
	}

	/**
	 * @return list<string>
	 */
	public function getAllCharactersOptions() : array {
		return $this->cache(__FUNCTION__, function() {
			$characters = $this->getAllCharacters();

			$options = [];
			foreach ($characters as $character) {
				$options[ $character->c['uuid'] ] = sprintf('%s (%s)', $character->c['name'], implode(', ', $character['movies']));
			}

			return $options;
		});
	}

	public function createCharacter(string $movieUuid, string $personUuid, string $name) : void {
		$this->db->execute(Query::make()
			->match('(p:Person {uuid: $p})', ['p' => $personUuid])
			->match('(m:Movie {uuid: $m})', ['m' => $movieUuid])
			->create('(c:Character {name: $name, uuid: $uuid})', [
				'name' => $name,
				'uuid' => $this->db->makeUuid(),
			])
			->create('(x:Role {fee: $fee})', ['fee' => rand(1000, 9999)])
			->create('(p)-[:HAS_ROLE]->(x)')
			->create('(c)-[:HAS_ROLE]->(x)')
			->create('(m)-[:HAS_ROLE]->(x)')
		);
	}

	public function createRole(string $movieUuid, string $personUuid, string $characterUuid) : void {
		$this->db->execute(Query::make()
			->match('(p:Person {uuid: $p})', ['p' => $personUuid])
			->match('(m:Movie {uuid: $m})', ['m' => $movieUuid])
			->match('(c:Character {uuid: $c})', ['c' => $characterUuid])
			->create('(x:Role {fee: $fee})', ['fee' => rand(1000, 9999)])
			->create('(p)-[:HAS_ROLE]->(x)')
			->create('(c)-[:HAS_ROLE]->(x)')
			->create('(m)-[:HAS_ROLE]->(x)')
		);
	}

	/**
	 * @return list<Container>
	 */
	public function getAllRoles() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(n:Role)<-[:HAS_ROLE]-(c:Character)')
				->match('(n)<-[:HAS_ROLE]-(m:Movie)')
				->match('(n)<-[:HAS_ROLE]-(p:Person)')
				->return('p, c, m, n.fee AS fee')
				->order('m.title, m.year, p.name')
			);
		});
	}

	public function getCharacter(string $uuid) : ?Container {
		return $this->db->oneNode(Query::make()
			->match('(c:Character {uuid: $uuid})', ['uuid' => $uuid])
			->return('c')
		);
	}

	/**
	 * @return list<Container>
	 */
	public function getCharacterAppearances(Container $character) : array {
		return $this->db->many(Query::make()
			->match('(c:Character {uuid: $uuid})-[:HAS_ROLE]->(r:Role)<-[:HAS_ROLE]-(p:Person)', ['uuid' => $character['uuid']])
			->match('(r)<-[:HAS_ROLE]-(m:Movie)')
			->return('p, m')
		);
	}

	/**
	 * @param AssocArray $data
	 */
	public function savePerson(?string $uuid, array $data) : void {
		$data = array_map('trim', $data);

		$this->db->merge('Person', $uuid, $data);
	}

	public function deletePerson(string $uuid) : void {
		$this->db->delete('Person', $uuid);
	}

	/**
	 * @param AssocArray $data
	 */
	public function saveMovie(?string $uuid, array $data) : void {
		$data = array_map('trim', $data);

		$this->db->merge('Movie', $uuid, $data);
	}

	public function deleteMovie(string $uuid) : void {
		$this->db->delete('Movie', $uuid);
	}

	/**
	 * @return list<AssocArray>
	 */
	public function getQueries() : array {
		$sessionQueries = $_SESSION['queries'] ?? [];
		$_SESSION['queries'] = [];
		return $sessionQueries;
	}

}
