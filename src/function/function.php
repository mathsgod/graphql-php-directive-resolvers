<?
use GraphQL\Language\AST\DirectiveNode;
use GuzzleHttp\Promise\Promise;

function getDirectiveArguments(DirectiveNode $directive)
{
    $args = [];
    foreach ($directive->arguments as $arg) {
        $args[$arg->name->value] = $arg->value;
    }
    return $args;
}

function defaultFieldResolver($source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info)
{
    $fieldName = $info->fieldName;
    $property = null;

    if (is_array($source) || $source instanceof \ArrayAccess) {
        if (isset($source[$fieldName])) {
            $property = $source[$fieldName];
        }
    } else if (is_object($source)) {
        if (isset($source->{$fieldName})) {
            $property = $source->{$fieldName};
        }
    }

    return $property instanceof Closure ? $property($source, $args, $context, $info) : $property;
}

function attachDirectiveResolvers($schema, $directiveDef)
{
    $schema_directives = $schema->getDirectives();
    foreach ($schema->getTypeMap() as $type) {
        if (!$type instanceof \GraphQL\Type\Definition\ObjectType) {
            continue;
        }
        foreach ($type->getFields() as $field) {
            $directives = [];
            foreach ($field->astNode->directives as $node) {
                $name = $node->name->value;
                $schema_directive = array_filter($schema_directives, function ($directive) use ($name) {
                    if ($directive->name == $name && in_array("FIELD_DEFINITION", $directive->locations)) {
                        return true;
                    }
                });

                if (!$schema_directive) {
                    continue;
                }

                $args = [];
                foreach (getDirectiveArguments($node) as $k => $v) {
                    if ($v->kind == "ListValue") {
                        $args[$k] = [];
                        foreach ($v->values as $node) {
                            $args[$k][] = $node->value;
                        }
                    } else {
                        $args[$k] = $v->value;
                    }
                }
                if (is_array($directiveDef)) {
                    $resolveFn = $directiveDef[$name];
                } else {
                    $resolveFn = function ($next, $source, $args, $context, $info) use ($directiveDef, $name) {
                        if (method_exists($directiveDef, $name)) {
                            return $directiveDef->$name($next, $source, $args, $context, $info);
                        } else {
                            return $next();
                        }
                    };
                }
                $directives[$node->name->value] = [
                    "name" => $node->name->value,
                    "args" => $args,
                    "resolveFn" => $resolveFn ? $resolveFn : function ($next) {
                        return $next();
                    }
                ];
            }
            $field->directives = $directives;

            $orginalResolveFn = $field->resolveFn;
            $field->resolveFn = function ($root, $args, $context, $info) use ($field, $orginalResolveFn) {
                $parent = new Promise();
                $p = $parent;
                foreach ($field->directives as $name => $directive) {
                    $p = function () use ($p) {
                        return $p;
                    };
                    $resolver = $directive["resolveFn"];
                    $p = $resolver($p, $root, $directive["args"], $context, $info);
                }
                if ($orginalResolveFn) {
                    $value = $orginalResolveFn($root, $args, $context, $info);
                } else {
                    $value = defaultFieldResolver($root, $args, $context, $info);
                }
                $parent->resolve($value);
                return $p->wait();
            };
        }
    }
}