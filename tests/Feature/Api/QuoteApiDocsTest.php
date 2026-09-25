<?php

namespace Tests\Feature\Api;

use App\Services\Quotes\QuoteRichText;
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

    public function test_quotes_store_documents_additional_services_as_object(): void
    {
        $spec = $this->get('/docs/api.json')->json();
        $requestBodySchema = $spec['paths']['/quotes']['post']['requestBody']['content']['application/json']['schema'] ?? null;

        $this->assertNotNull($requestBodySchema, 'Expected a request body schema for POST /quotes.');

        $variants = $requestBodySchema['allOf'] ?? [$requestBodySchema];
        $additionalServicesSchema = collect($variants)
            ->pluck('properties.additional_services')
            ->filter()
            ->last();

        $this->assertNotNull($additionalServicesSchema, 'Expected additional_services to be documented on POST /quotes.');
        $this->assertEquals('object', $additionalServicesSchema['type'] ?? null, 'Expected additional_services to be documented as an object, not an array of strings.');
    }

    private function spec(): array
    {
        return $this->spec ??= $this->get('/docs/api.json')->json();
    }

    private ?array $spec = null;

    private function allFullQuoteSchemas(): array
    {
        $spec = $this->spec();
        $ops = [
            ['/quotes', 'get', '200'], ['/quotes/{quote}', 'get', '200'],
            // Scramble documenta store sotto 200 anche se risponde 201 (limite noto, oc:8631).
            ['/quotes', 'post', '200'],
            ['/quotes/{quote}', 'patch', '200'],
            ['/quotes/{quote}/products/{product}', 'post', '200'], ['/quotes/{quote}/products/{product}', 'delete', '200'],
            ['/quotes/{quote}/recurring-products/{recurringProduct}', 'post', '200'],
            ['/quotes/{quote}/recurring-products/{recurringProduct}', 'delete', '200'],
        ];

        $schemas = [];
        foreach ($ops as [$path, $method, $status]) {
            $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? null;
            $this->assertNotNull($schema, "Expected a {$status} response schema for {$method} {$path}.");
            $schemas["{$method} {$path}"] = $schema;
        }
        return $schemas;
    }

    /**
     * Le proprietà del preventivo in ogni forma di risposta documentata: per
     * index sia l'array semplice sia la variante paginata {data, meta}.
     *
     * @return array<int, array>
     */
    private function quotePropertySets(array $schema): array
    {
        $sets = [];
        foreach ($schema['anyOf'] ?? [$schema] as $variant) {
            if (($variant['type'] ?? null) === 'array') {
                $variant = $variant['items'];
            } elseif (isset($variant['properties']['data'])) {
                $variant = $variant['properties']['data']['items'] ?? [];
            }
            $sets[] = $variant['properties'] ?? [];
        }
        return $sets;
    }

    /** Proprietà del body della richiesta, risolvendo il $ref a components/schemas. */
    private function requestBodyProperties(string $path, string $method): array
    {
        $spec = $this->spec();
        $body = $spec['paths'][$path][$method]['requestBody']['content']['application/json']['schema'] ?? [];

        return collect($body['allOf'] ?? [$body])
            ->map(fn ($part) => isset($part['$ref'])
                ? $spec['components']['schemas'][basename($part['$ref'])] ?? []
                : $part)
            ->pluck('properties')->filter()
            ->reduce(fn (array $carry, array $properties) => array_replace_recursive($carry, $properties), []);
    }

    public function test_all_full_quote_responses_document_the_rich_text_fields(): void
    {
        foreach ($this->allFullQuoteSchemas() as $operation => $schema) {
            foreach ($this->quotePropertySets($schema) as $variant => $properties) {
                foreach (QuoteRichText::FIELDS as $field) {
                    $this->assertArrayHasKey($field, $properties, "Expected {$field} in response variant {$variant} of {$operation}.");
                }
            }
        }
    }

    public function test_quotes_store_and_update_document_the_rich_text_fields_in_the_body(): void
    {
        foreach ([['/quotes', 'post'], ['/quotes/{quote}', 'patch']] as [$path, $method]) {
            $properties = $this->requestBodyProperties($path, $method);
            foreach (QuoteRichText::FIELDS as $field) {
                $this->assertArrayHasKey($field, $properties, "Expected {$field} in the request body of {$method} {$path}.");
            }
        }
    }

    public function test_quotes_store_and_update_describe_the_rich_text_rules_in_the_body(): void
    {
        foreach ([['/quotes', 'post'], ['/quotes/{quote}', 'patch']] as [$path, $method]) {
            $properties = $this->requestBodyProperties($path, $method);
            foreach (QuoteRichText::FIELDS as $field) {
                $description = $properties[$field]['description'] ?? '';
                $this->assertStringContainsString('HTML', $description, "Expected {$method} {$path} to describe {$field} as HTML.");
                $this->assertStringContainsString('/storage/', $description, "Expected {$method} {$path} to state the image rule for {$field}.");
            }
        }
    }
}
