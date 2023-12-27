<?php

namespace Utopia\Migration\Schemas;

class Schema
{
    public $schema = [];

    public function add(string $name, array $schema): self
    {
        $this->schema[$name] = $schema;

        return $this;
    }

    public function validate(array $data, string $name): SchemaResponse
    {
        $errors = [];

        foreach ($this->schema[$name] as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $errors[$key] = 'Key not provided';
                continue;
            }

            if (is_array($value)) {
                $nestedResult = $this->validateNested($data[$key], $value);
                if (!$nestedResult->isValid()) {
                    $errors[$key] = $nestedResult->getErrors();
                }
            } else {
                if (!$value->isValid($data[$key])) {
                    $errors[$key] = 'Invalid value';
                }
            }
        }

        return new SchemaResponse(count($errors) === 0, $errors);
    }

    private function validateNested(array $data, array $validators): SchemaResponse
    {
        $errors = [];
    
        foreach ($validators as $key => $validator) {
            if (!array_key_exists($key, $data)) {
                $errors[$key] = 'Key not provided';
                continue;
            }
    
            if (is_array($validator)) {
                $nestedResult = $this->validateNested($data[$key], $validator);
                if (!$nestedResult->isValid()) {
                    $errors[$key] = $nestedResult->getErrors();
                }
            } else {
                if (!$validator->isValid($data[$key])) {
                    $errors[$key] = 'Invalid value';
                }
            }
        }
    
        return new SchemaResponse(count($errors) === 0, $errors);
    }
}
