<?php

declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

final class Test extends TestCase
{
    public function test_directive_resolver()
    {
        $schema = BuildSchema::build(file_get_contents(__DIR__ . "/schema.gql"));
        $schema->getType("Query")->getField("me")->resolveFn = function ($root, $args, $context, $info) {
            return ["first_name" => "my first name"];
        };

        //--- resolver 
        $directiveResolvers = [
            "upper" => function ($next, $source, $args, $context) {
                return $next()->then(function ($str) {
                    return strtoupper($str);
                });
            }
        ];

        attachDirectiveResolvers($schema, $directiveResolvers);

        $query = "query{
            me{
                first_name
            }
        }
        ";
        $result = GraphQL::executeQuery($schema, $query);
        $result = $result->toArray();

        $this->assertEquals("MY FIRST NAME", $result["data"]["me"]["first_name"]);
    }
}
