<?php

use rdx\graphdb\Container;
use rdx\graphdb\Database;
use rdx\graphdb\Query;

class FriendsApp {

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

		// CREATE CONSTRAINT FOR (p:Person) REQUIRE p.name IS UNIQUE
	}

	protected function cache(string $name, Closure $worker) : mixed {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	public function findFriendshipPath(string $person1, string $person2) : ?Container {
		return $this->db->one('
			MATCH path=(e:Person {name: $person1})-[f:IS_FRIENDS_WITH*]-(j:Person {name: $person2})
			RETURN nodes(path) AS friends, size(f) AS length
			ORDER BY length ASC
			LIMIT 1
		', compact('person1', 'person2'));
	}

	public function deleteFriendship(string $id) : void {
		$this->db->execute(Query::make()
			->match('()-[f:IS_FRIENDS_WITH]->()')
			->where('elementId(f) = $id', ['id' => $id])
			->delete('f')
		);
	}

	public function createFriendship(string $person1, string $person2) : void {
		$data = ['since' => time()];
		$this->db->execute(Query::make()
			->match('(p1:Person)')->match('(p2:Person)')
			->where('p1.name = $person1', compact('person1'))
			->where('p2.name = $person2', compact('person2'))
			->create('(p1)-[:IS_FRIENDS_WITH $data]->(p2)', compact('data'))
		);
	}

	public function deletePerson(string $name) : void {
		$this->db->execute(Query::make()
			->match('(p:Person)')
			->where('p.name = $name', compact('name'))
			->delete('p')
		);
	}

	/**
	 * @param AssocArray $data
	 */
	public function createPerson(array $data) : void {
		$data = array_map('trim', $data);

		// Person.name is UNIQUE, so this is a very easy MERGE/REPLACE/UPSERT
		$this->db->execute(Query::make()
			->merge('(p:Person {name: $name})', ['name' => $data['name']])
			->set('p += $data', ['data' => $data])
		);
	}

	/**
	 * @return list<Container>
	 */
	public function getAllFriendships() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(p1)-[f:IS_FRIENDS_WITH]->(p2)')
				->return('p1.name AS name1', 'p2.name AS name2', 'f.since AS since', 'elementId(f) AS fid')
				->order('name1 ASC', 'name2 ASC')
			);
		});
	}

	/**
	 * @return list<Container>
	 */
	public function getAllPeople() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many(Query::make()
				->match('(p:Person)')
				->return('p')
				->order('p.name ASC')
			);
		});
	}

	/**
	 * @return array<string, string>
	 */
	public function getAllPeopleOptions() : array {
		return $this->cache(__FUNCTION__, function() {
			$people = $this->getAllPeople();

			$options = [];
			foreach ($people as $person) {
				$props = array_filter([$person->p['age'], $person->p['hobby']]);
				$options[ $person->p['name'] ] = sprintf('%s (%s)', $person->p['name'], implode(', ', $props) ?: '-');
			}

			return $options;
		});
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
