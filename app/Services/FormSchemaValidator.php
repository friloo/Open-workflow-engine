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
                case 'file':
                    // File rule: only validate via the request->file() side; we just
                    // mark as nullable string for the JSON-data check below.
                    $rule = [($field['required'] ?? false) ? 'required' : 'nullable', 'file', 'max:15360'];
                    break;
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
            if (($field['type'] ?? null) === 'file') {
                // File-Werte landen separat als Attachment, hier nur Platzhalter
                $v = $v instanceof \Illuminate\Http\UploadedFile ? '[file]' : null;
            }
            $clean[$key] = $v;
        }
        return $clean;
    }

    /**
     * Liefert die Felder vom Typ "file", die im Schema definiert sind.
     */
    public function fileFields(array $schema): array
    {
        return array_values(array_filter($schema, fn ($f) => ($f['type'] ?? null) === 'file'));
    }
}
