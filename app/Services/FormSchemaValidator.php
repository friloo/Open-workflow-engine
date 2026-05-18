<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormSchemaValidator
{
    /**
     * Build Laravel validation rules from a form schema definition and run the validator.
     *
     * @return array Sanitised payload keyed by field key.
     */
    public function validateAgainstSchema(array $input, array $schema): array
    {
        $rules = [];
        $attributes = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (! $key) continue;
            $attributes[$key] = $field['label'] ?? $key;
            $rule = [];
            $rule[] = ($field['required'] ?? false) ? 'required' : 'nullable';
            switch ($field['type'] ?? 'text') {
                case 'number': $rule[] = 'numeric'; break;
                case 'date': $rule[] = 'date'; break;
                case 'select':
                case 'radio':
                    $opts = $field['options'] ?? [];
                    if ($opts) $rule[] = 'in:'.implode(',', $opts);
                    break;
                case 'checkbox': $rule[] = 'in:0,1,true,false'; break;
                case 'text': case 'textarea': $rule[] = 'string'; $rule[] = 'max:5000'; break;
            }
            $rules[$key] = $rule;
        }
        $rules['_honeypot'] = ['nullable', 'size:0']; // anti-spam

        $validator = Validator::make($input, $rules, [], $attributes);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $clean = [];
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (! $key) continue;
            $v = $input[$key] ?? null;
            if (($field['type'] ?? null) === 'checkbox') {
                $v = in_array($v, ['1', 1, true, 'true'], true);
            }
            $clean[$key] = $v;
        }
        return $clean;
    }
}
