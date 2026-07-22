<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_ui_is_publicly_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
    }

    public function test_openapi_spec_is_publicly_accessible_and_valid_json(): void
    {
        $response = $this->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
        $this->assertIsArray($response->json('paths'));
    }

    public function test_mutating_operations_have_no_security_requirement_in_spec(): void
    {
        $spec = $this->get('/docs/api.json')->json();

        $storyPostOperation = $spec['paths']['/stories']['post'] ?? null;
        $this->assertNotNull($storyPostOperation, 'Expected POST /stories in generated spec.');
        $this->assertSame([], $storyPostOperation['security'] ?? null);
    }

    public function test_readonly_operations_inherit_global_security_requirement(): void
    {
        $spec = $this->get('/docs/api.json')->json();

        $storyShowOperation = $spec['paths']['/stories/{story}']['get'] ?? null;
        $this->assertNotNull($storyShowOperation, 'Expected GET /stories/{story} in generated spec.');
        $this->assertArrayNotHasKey('security', $storyShowOperation, 'GET operations should inherit the global security requirement, not override it.');
        $this->assertNotEmpty($spec['security'] ?? [], 'Global document security requirement (Bearer) must be set.');
    }
}
