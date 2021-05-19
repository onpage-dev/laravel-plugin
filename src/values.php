<?php

// Create all resources in db
// Create all fields in db
// Create all things and values in db
//$schema = ...;

foreach ($res->data as $thing) {

    foreach ($thing->fields as $name => $values) {
        $parts = explode('_', $name); // ['1234', 'en'] oppure ['1234']
        $field_id = $parts[0]; // 1234
        $lang = @$parts[1]; // 'it' oppure null

        $field = Field::find($field_id);
        if (!$field->is_multiple) {
            $values = [$values];
        }
        
        foreach ($values as $value) {
            // value can be SCALAR, FILE, DIM2, DIM3
            $data = null;
            switch ($field->type) {
                case 'file':
                case 'image':
                    $data = [
                        'value_txt' => $value->name,
                        'value_token' => $value->token,
                    ];
                    break;
                case 'dim2':
                case 'dim3':
                    $data = [
                        'value_real0' => @$value[0],
                        'value_real1' => @$value[1],
                        'value_real2' => @$value[2],
                    ];
                    break;
                case 'int':
                case 'real':
                    $data = [
                        'value_real0' => $value,
                    ];
                    break;
                default:
                    $data = [
                        'value_txt' => $value,
                    ];
            }
            Value::create([
                'thing_id' => $thing->id,
                'field_id' => $field->id,
                'lang' => $lang,
            ] + $data)
        }
    }
}

$prodotto = OnPage\Prodotto::first();
$prodotto->val('nome', 'fr'); // description

class Thing {M
    function val(string $field_name, string $lang = null) {
        $field = Field::where('name', $field_name)->first();
        if (!$field) return null;
        $values = $this->values()
            ->where('field_id', $field->id)
            ->where('lang', $lang)
            ->get();
        // [
        //     Value {
        //         ...
        //         value_txt: 'description'
        //         lang: 'fr'
        //     }
        // ]
        $ret = [];
        foreach ($values as $value) {
            switch ($field->type) {
                case 'file':
                case 'image':
                    $ret[] = [
                        'name' => $value->value_txt,
                        'token' => $value->value_token,
                    ];
                    break;
                case 'dim2':
                case 'dim3':
                    $ret[] = [
                        $value->value_real0,
                        $value->value_real1,
                        $value->value_real2,
                    ];
                    break;
                case 'int':
                case 'real':
                    $ret[] = $value->value_real0;
                    break;
                default:
                    $ret[] = $value->value_txt;
            }
        }
        // $ret = ['description']
        if ($field->is_multiple) {
            return $ret; // ['description']
        } else {
            return @$ret[0]; // 'description'
        }
    }
}