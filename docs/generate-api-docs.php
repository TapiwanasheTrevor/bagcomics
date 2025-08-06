<?php

/**
 * API Documentation Generator
 * 
 * This script generates comprehensive API documentation from the OpenAPI specification
 * and creates additional documentation files for developers.
 */

require_once __DIR__ . '/../vendor/autoload.php';

class ApiDocumentationGenerator
{
    private string $baseDir;
    private array $apiSpec;

    public function __construct()
    {
        $this->baseDir = __DIR__;
        $this->loadApiSpec();
    }

    private function loadApiSpec(): void
    {
        $yamlContent = file_get_contents($this->baseDir . '/api-documentation.yaml');
        $this->apiSpec = yaml_parse($yamlContent);
    }

    public function generateMarkdownDocs(): void
    {
        $this->generateOverviewDoc();
        $this->generateEndpointDocs();
        $this->generateAuthenticationDoc();
        $this->generateErrorHandlingDoc();
        $this->generateExamplesDoc();
        $this->generatePostmanCollection();
    }

    private function generateOverviewDoc(): void
    {
        $content = "# API Documentation Overview\n\n";
        $content .= $this->apiSpec['info']['description'] . "\n\n";
        
        $content .= "## Base URLs\n\n";
        foreach ($this->apiSpec['servers'] as $server) {
            $content .= "- **{$server['description']}**: `{$server['url']}`\n";
        }
        
        $content .= "\n## API Version\n\n";
        $content .= "Current version: " . $this->apiSpec['info']['version'] . "\n\n";
        
        $content .= "## Rate Limiting\n\n";
        $content .= "- Public routes: 120 requests per minute\n";
        $content .= "- Authenticated routes: 300 requests per minute\n";
        $content .= "- Admin routes: 200 requests per minute\n\n";
        
        $content .= "## Response Format\n\n";
        $content .= "All API responses follow a consistent JSON format:\n\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"success\": true,\n";
        $content .= "  \"data\": {...},\n";
        $content .= "  \"timestamp\": \"2025-08-06T12:00:00Z\"\n";
        $content .= "}\n";
        $content .= "```\n\n";
        
        file_put_contents($this->baseDir . '/API_OVERVIEW.md', $content);
    }

    private function generateEndpointDocs(): void
    {
        $content = "# API Endpoints Reference\n\n";
        
        $groupedPaths = $this->groupPathsByTag();
        
        foreach ($groupedPaths as $tag => $paths) {
            $content .= "## {$tag}\n\n";
            
            foreach ($paths as $path => $methods) {
                foreach ($methods as $method => $details) {
                    $content .= "### {$method} {$path}\n\n";
                    $content .= $details['summary'] . "\n\n";
                    
                    if (isset($details['description'])) {
                        $content .= $details['description'] . "\n\n";
                    }
                    
                    // Parameters
                    if (isset($details['parameters'])) {
                        $content .= "#### Parameters\n\n";
                        $content .= "| Name | Type | Required | Description |\n";
                        $content .= "|------|------|----------|-------------|\n";
                        
                        foreach ($details['parameters'] as $param) {
                            $required = isset($param['required']) && $param['required'] ? 'Yes' : 'No';
                            $type = $param['schema']['type'] ?? 'string';
                            $description = $param['description'] ?? '';
                            $content .= "| {$param['name']} | {$type} | {$required} | {$description} |\n";
                        }
                        $content .= "\n";
                    }
                    
                    // Request Body
                    if (isset($details['requestBody'])) {
                        $content .= "#### Request Body\n\n";
                        $content .= "```json\n";
                        $content .= $this->generateExampleFromSchema($details['requestBody']);
                        $content .= "\n```\n\n";
                    }
                    
                    // Responses
                    if (isset($details['responses'])) {
                        $content .= "#### Responses\n\n";
                        foreach ($details['responses'] as $code => $response) {
                            $content .= "**{$code}**: {$response['description']}\n\n";
                        }
                    }
                    
                    $content .= "---\n\n";
                }
            }
        }
        
        file_put_contents($this->baseDir . '/API_ENDPOINTS.md', $content);
    }

    private function generateAuthenticationDoc(): void
    {
        $content = "# Authentication\n\n";
        $content .= "The API uses Laravel Sanctum for authentication. To authenticate requests:\n\n";
        $content .= "1. Obtain an API token by logging in through the web interface or authentication endpoints\n";
        $content .= "2. Include the token in the Authorization header:\n\n";
        $content .= "```\nAuthorization: Bearer YOUR_API_TOKEN\n```\n\n";
        $content .= "## Authentication Endpoints\n\n";
        $content .= "- `POST /login` - Authenticate and receive token\n";
        $content .= "- `POST /logout` - Invalidate current token\n";
        $content .= "- `POST /register` - Create new account\n\n";
        $content .= "## Token Management\n\n";
        $content .= "- Tokens do not expire by default\n";
        $content .= "- Users can have multiple active tokens\n";
        $content .= "- Tokens can be revoked individually\n\n";
        $content .= "## Admin Access\n\n";
        $content .= "Admin endpoints require both authentication and the `access-admin` permission.\n";
        
        file_put_contents($this->baseDir . '/AUTHENTICATION.md', $content);
    }

    private function generateErrorHandlingDoc(): void
    {
        $content = "# Error Handling\n\n";
        $content .= "The API uses standard HTTP status codes and returns consistent error responses.\n\n";
        $content .= "## Error Response Format\n\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"success\": false,\n";
        $content .= "  \"error\": {\n";
        $content .= "    \"code\": \"ERROR_CODE\",\n";
        $content .= "    \"message\": \"Human readable error message\",\n";
        $content .= "    \"timestamp\": \"2025-08-06T12:00:00Z\"\n";
        $content .= "  }\n";
        $content .= "}\n";
        $content .= "```\n\n";
        
        $content .= "## Common Error Codes\n\n";
        $content .= "| HTTP Status | Error Code | Description |\n";
        $content .= "|-------------|------------|-------------|\n";
        $content .= "| 400 | BAD_REQUEST | Invalid request format or parameters |\n";
        $content .= "| 401 | UNAUTHORIZED | Authentication required |\n";
        $content .= "| 403 | FORBIDDEN | Insufficient permissions |\n";
        $content .= "| 404 | NOT_FOUND | Resource not found |\n";
        $content .= "| 422 | VALIDATION_ERROR | Request validation failed |\n";
        $content .= "| 429 | TOO_MANY_REQUESTS | Rate limit exceeded |\n";
        $content .= "| 500 | INTERNAL_SERVER_ERROR | Server error |\n\n";
        
        $content .= "## Validation Errors\n\n";
        $content .= "Validation errors include additional details:\n\n";
        $content .= "```json\n";
        $content .= "{\n";
        $content .= "  \"success\": false,\n";
        $content .= "  \"error\": {\n";
        $content .= "    \"code\": \"VALIDATION_ERROR\",\n";
        $content .= "    \"message\": \"The given data was invalid.\",\n";
        $content .= "    \"validation_errors\": {\n";
        $content .= "      \"email\": [\"The email field is required.\"],\n";
        $content .= "      \"rating\": [\"The rating must be between 1 and 5.\"]\n";
        $content .= "    },\n";
        $content .= "    \"timestamp\": \"2025-08-06T12:00:00Z\"\n";
        $content .= "  }\n";
        $content .= "}\n";
        $content .= "```\n";
        
        file_put_contents($this->baseDir . '/ERROR_HANDLING.md', $content);
    }

    private function generateExamplesDoc(): void
    {
        $content = "# API Usage Examples\n\n";
        
        $examples = [
            'Get Comics List' => [
                'method' => 'GET',
                'url' => '/api/comics?genre=Superhero&sort_by=rating&sort_order=desc',
                'description' => 'Retrieve superhero comics sorted by rating'
            ],
            'Search Comics' => [
                'method' => 'GET',
                'url' => '/api/search/comics?query=spider-man&min_rating=4.0',
                'description' => 'Search for Spider-Man comics with high ratings'
            ],
            'Update Reading Progress' => [
                'method' => 'POST',
                'url' => '/api/comics/1/progress/update',
                'body' => [
                    'current_page' => 15,
                    'reading_time_seconds' => 900,
                    'device_type' => 'mobile'
                ],
                'description' => 'Update reading progress for a comic'
            ],
            'Submit Review' => [
                'method' => 'POST',
                'url' => '/api/reviews/comics/1',
                'body' => [
                    'rating' => 5,
                    'title' => 'Amazing story!',
                    'content' => 'This comic has an incredible storyline and beautiful artwork.',
                    'is_spoiler' => false
                ],
                'description' => 'Submit a review for a comic'
            ],
            'Create Payment Intent' => [
                'method' => 'POST',
                'url' => '/api/payments/comics/1/intent',
                'body' => [
                    'payment_method' => 'card',
                    'currency' => 'USD'
                ],
                'description' => 'Create a payment intent for purchasing a comic'
            ]
        ];
        
        foreach ($examples as $title => $example) {
            $content .= "## {$title}\n\n";
            $content .= $example['description'] . "\n\n";
            $content .= "**Request:**\n";
            $content .= "```http\n";
            $content .= "{$example['method']} {$example['url']}\n";
            $content .= "Authorization: Bearer YOUR_API_TOKEN\n";
            $content .= "Content-Type: application/json\n";
            
            if (isset($example['body'])) {
                $content .= "\n" . json_encode($example['body'], JSON_PRETTY_PRINT) . "\n";
            }
            
            $content .= "```\n\n";
            $content .= "---\n\n";
        }
        
        file_put_contents($this->baseDir . '/API_EXAMPLES.md', $content);
    }

    private function generatePostmanCollection(): void
    {
        $collection = [
            'info' => [
                'name' => 'Comprehensive Comic Platform API',
                'description' => $this->apiSpec['info']['description'],
                'version' => $this->apiSpec['info']['version']
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{api_token}}',
                        'type' => 'string'
                    ]
                ]
            ],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => 'http://localhost:8000/api',
                    'type' => 'string'
                ],
                [
                    'key' => 'api_token',
                    'value' => 'your_api_token_here',
                    'type' => 'string'
                ]
            ],
            'item' => []
        ];
        
        $groupedPaths = $this->groupPathsByTag();
        
        foreach ($groupedPaths as $tag => $paths) {
            $folder = [
                'name' => $tag,
                'item' => []
            ];
            
            foreach ($paths as $path => $methods) {
                foreach ($methods as $method => $details) {
                    $request = [
                        'name' => $details['summary'],
                        'request' => [
                            'method' => strtoupper($method),
                            'header' => [
                                [
                                    'key' => 'Content-Type',
                                    'value' => 'application/json'
                                ]
                            ],
                            'url' => [
                                'raw' => '{{base_url}}' . $path,
                                'host' => ['{{base_url}}'],
                                'path' => explode('/', trim($path, '/'))
                            ]
                        ]
                    ];
                    
                    if (isset($details['requestBody'])) {
                        $request['request']['body'] = [
                            'mode' => 'raw',
                            'raw' => $this->generateExampleFromSchema($details['requestBody'])
                        ];
                    }
                    
                    $folder['item'][] = $request;
                }
            }
            
            $collection['item'][] = $folder;
        }
        
        file_put_contents($this->baseDir . '/postman-collection.json', json_encode($collection, JSON_PRETTY_PRINT));
    }

    private function groupPathsByTag(): array
    {
        $grouped = [];
        
        foreach ($this->apiSpec['paths'] as $path => $methods) {
            foreach ($methods as $method => $details) {
                $tags = $details['tags'] ?? ['Uncategorized'];
                $tag = $tags[0];
                
                if (!isset($grouped[$tag])) {
                    $grouped[$tag] = [];
                }
                
                if (!isset($grouped[$tag][$path])) {
                    $grouped[$tag][$path] = [];
                }
                
                $grouped[$tag][$path][$method] = $details;
            }
        }
        
        return $grouped;
    }

    private function generateExampleFromSchema($requestBody): string
    {
        // This is a simplified example generator
        // In a real implementation, you'd parse the schema more thoroughly
        return json_encode([
            'example' => 'data'
        ], JSON_PRETTY_PRINT);
    }
}

// Generate documentation
$generator = new ApiDocumentationGenerator();
$generator->generateMarkdownDocs();

echo "API documentation generated successfully!\n";
echo "Files created:\n";
echo "- API_OVERVIEW.md\n";
echo "- API_ENDPOINTS.md\n";
echo "- AUTHENTICATION.md\n";
echo "- ERROR_HANDLING.md\n";
echo "- API_EXAMPLES.md\n";
echo "- postman-collection.json\n";