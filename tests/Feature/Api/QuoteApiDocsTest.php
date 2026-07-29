<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Regression guard for /docs/api drift: this ticket (oc:8291) repeatedly found,
 * only via manual inspection, that the generated OpenAPI schema silently
 * disagreed with what the Quote API endpoints actually return/accept
 * (missing sort/page/include query params, missing company_name on the
 * embedded customer object, undocumented paginated response shape). These
 * tests catch that class of regression automatically.
 */
class QuoteApiDocsTest extends TestCase
{
    private function operation(string $path, string $method): array
    {
        $spec = $this->get('/docs/api.json')->json();
        $operation = $spec['paths'][$path][$method] ?? null;

        $this->assertNotNull($operation, "Expected {$method} {$path} in generated spec.");

        return $operation;
    }

    private function queryParameterNames(string $path, string $method): array
    {
        return collect($this->operation($path, $method)['parameters'] ?? [])->pluck('name')->all();
    }

    private function okResponseSchema(string $path, string $method): array
    {
        $schema = $this->operation($path, $method)['responses']['200']['content']['application/json']['schema'] ?? null;

        $this->assertNotNull($schema, "Expected a 200 response schema for {$method} {$path}.");

        return $schema;
    }

    public function test_quotes_index_documents_all_filter_and_pagination_parameters(): void
    {
        $names = $this->queryParameterNames('/quotes', 'get');

        foreach (['customer_id', 'status', 'sort', 'per_page', 'page'] as $expected) {
            $this->assertContains($expected, $names, "Expected query parameter \"{$expected}\" to be documented on GET /quotes.");
        }
    }

    public function test_quotes_show_documents_include_parameter(): void
    {
        $names = $this->queryParameterNames('/quotes/{quote}', 'get');

        $this->assertContains('include', $names, 'Expected query parameter "include" to be documented on GET /quotes/{quote}.');
    }

    public function test_quotes_show_response_schema_includes_company_name_on_embedded_customer(): void
    {
        $schema = $this->okResponseSchema('/quotes/{quote}', 'get');

        $customerProperties = $schema['properties']['customer']['properties'] ?? [];

        $this->assertArrayHasKey('company_name', $customerProperties, 'Expected the embedded customer object in GET /quotes/{quote} to document company_name.');
    }

    public function test_quotes_index_response_schema_documents_both_plain_array_and_paginated_shapes(): void
    {
        $schema = $this->okResponseSchema('/quotes', 'get');

        $variants = $schema['anyOf'] ?? [$schema];

        $hasPlainArray = collect($variants)->contains(fn($v) => ($v['type'] ?? null) === 'array');
        $this->assertTrue($hasPlainArray, 'Expected GET /quotes to document the plain-array response (no per_page/page).');

        $paginated = collect($variants)->first(fn($v) => isset($v['properties']['data'], $v['properties']['meta']));
        $this->assertNotNull($paginated, 'Expected GET /quotes to document the paginated {data, meta} response.');
        foreach (['current_page', 'per_page', 'total', 'last_page'] as $metaField) {
            $this->assertArrayHasKey($metaField, $paginated['properties']['meta']['properties'] ?? [], "Expected meta.{$metaField} in the paginated response schema.");
        }
    }
}
