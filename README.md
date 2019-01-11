```php

require_once(__DIR__ . "/../vendor/autoload.php");
use GraphQL\Utils\BuildSchema;
use GraphQL\GraphQL;

$schema_gql = <<<gql
directive @upper on FIELD_DEFINITION

schema {
    query: Query
}

type Query {
    me: User
}

type User {
    first_name:String @upper
    last_name:String
}
gql;

$schema = BuildSchema::build($schema_gql);

$schema->getType("Query")->getField("me")->resolveFn = function ($root, $args, $context, $info) {
    return ["first_name" => "my first name"];
};

$directiveResolvers = [
    "upper" => function ($next, $source, $args, $context) {
        return $next()->then(function ($str) {
            return strtoupper($str);
        });
    }
];

attachDirectiveResolvers($schema, $directiveResolvers);

//----- query data

$query = "query{
    me{
        first_name
    }
}
";
$result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues, $operationName);
$result = $result->toArray();

print_r($result);
```

# Example
Schema:
```
directive @upper on FIELD_DEFINITION

schema {
    query: Query
}

type Query {
    me: User
}

type User {
    first_name:String @upper
    last_name:String
}
```


Input: 
```
query{
    me{
        first_name
    }
}
```

Result:

```
Array
(
    [data] => Array
        (
            [me] => Array
                (
                    [first_name] => MY FIRST NAME
                )

        )

)
```


