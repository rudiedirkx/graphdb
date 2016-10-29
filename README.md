Graph DB
====

Set up:
----

	use GraphAware\Neo4j\Client\ClientBuilder;
	use rdx\graphdb\Database;

	// Use graphaware/neo4j-php-client for the complicated connection stuff
	$client = ClientBuilder::create()
		->addConnection('default', CONNECTION_URL_HERE)
		->build();

	// Add a very simple wrapper for easy access
	$db = new Database($client);

Get & do stuff:
----

`one(query, params)`:

	$user = $db->one('MATCH (u:User) WHERE u.name = {name} RETURN u', ['name' => 'Alice']);

`many(query, params)`:

	$users = $db->many('MATCH (u:User) WHERE u.age > {age} RETURN u.name, u.age', ['age' => 30]);

`execute(query, params)`:

	$data = ['age' => 30, 'hobbies' => ['parking', 'waiting']];
	$db->execute('MATCH (u:User) WHERE u.name = {name} SET u += {data}', compact('name', 'data'));

Query builder:
----

Works for `one()`, `many()` and `execute()`. Takes care of `params`.

	use rdx\graphdb\Query;

	$data = ['since' => time()];
	$db->execute(Query::make()
		->match('(p1:Person)')
		->match('(p2:Person)')
		->where('p1.name = {person1}', compact('person1'))
		->where('p2.name = {person2}', compact('person2'))
		->create('(p1)-[:IS_FRIENDS_WITH {data}]->(p2)', compact('data'))
	);

	$db->many(Query::make()
		->match('(p1)-[f:IS_FRIENDS_WITH]->(p2)')
		->return('p1', 'p2', 'f.since AS since', 'id(f) AS fid')
		->order('p1.name ASC', 'p2.name ASC')
	);
