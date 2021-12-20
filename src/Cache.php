<?php

namespace OnPage;

use Illuminate\Support\Collection;

class Cache
{
    private static $id_to_resource = [];
    private static $name_to_resource = [];
    private static $id_to_field = [];
    private static $name_to_field = [];

    public static function refresh()
    {
        self::$id_to_resource = [];
        self::$name_to_resource = [];
        self::$id_to_field = [];
        self::$name_to_field = [];

        try {
            foreach (Models\Resource::with([
                'fields' => function ($q) {
                    $q->sorted();
                }
            ])->get() as $res) {
                self::$id_to_resource[$res->id] = $res;
                self::$name_to_resource[$res->name] = $res;

                foreach ($res->fields as $field) {
                    self::$id_to_field[$field->id] = $field;
                    self::$name_to_field["{$res->id}-{$field->name}"] = $field;
                }
            }
        } catch (\Illuminate\Database\QueryException $th) {
            // Migrations have not been run yet
        }
    }

    static function nameToResource(string $name): ?Models\Resource
    {
        return @self::$name_to_resource[$name];
    }

    static function idToResource(string $id): ?Models\Resource
    {
        return @self::$id_to_resource[$id];
    }

    static function idToField(string $id): ?Models\Field
    {
        return @self::$id_to_field[$id];
    }

    static function nameToField(int $resource_id, string $name): ?Models\Field
    {
        return @self::$name_to_field["$resource_id-$name"];
    }

    static function resources(): Collection
    {
        return collect(array_values(self::$id_to_resource));
    }
}
