<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\Controller\Api;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Company;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FormApiControllerFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private const TEST_PAYLOAD = [
        'name'        => 'API form',
        'description' => 'Form created via API test',
        'formType'    => 'standalone',
        'isPublished' => true,
        'fields'      => [
            [
                'label'        => 'Email',
                'type'         => 'text',
                'alias'        => 'email',
                'mappedObject' => 'contact',
                'mappedField'  => 'email',
                'showLabel'    => true,
                'isRequired'   => true,
            ],
            [
                'label'        => 'Number',
                'type'         => 'number',
                'alias'        => 'number',
                'leadField'    => 'points', // @deprecated Setting leadField, no mappedField or mappedObject (BC).
            ],
            [
                'label'        => 'Company',
                'type'         => 'text',
                'alias'        => 'company',
                'leadField'    => 'company', // @deprecated Setting leadField, no mappedField or mappedObject (BC).
            ],
            [
                'label'        => 'Company Phone',
                'type'         => 'tel',
                'alias'        => 'phone',
                'leadField'    => 'companyphone', // @deprecated Setting leadField, no mappedField or mappedObject (BC).
            ],
            [
                'label'        => 'Country',
                'type'         => 'country',
                'alias'        => 'country',
                'mappedObject' => 'contact',
                'mappedField'  => 'country',
            ],
            [
                'label'        => 'Multiselect',
                'type'         => 'select',
                'alias'        => 'multiselect',
                'properties'   => [
                    'syncList' => 0,
                    'multiple' => 1,
                    'list'     => [
                        'list' => [
                            [
                                'label' => 'One',
                                'value' => 'one',
                            ],
                            [
                                'label' => 'Two',
                                'value' => 'two',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label'        => 'Submit',
                'type'         => 'button',
            ],
        ],
        'actions' => [
        ],
    ];

    public function testFormWorkflow(): void
    {
        $payload = [
            'name'        => 'Form API test',
            'formType'    => 'standalone',
            'isPublished' => true,
            'description' => 'Functional API test',
            'fields'      => [
                [
                    'label'     => 'Email',
                    'alias'     => 'email',
                    'type'      => 'text',
                    'leadField' => 'email',
                ],
            ],
        ];

        // Create:
        $this->client->request('POST', '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);

        if (!empty($response['errors'][0])) {
            $this->fail($response['errors'][0]['code'].': '.$response['errors'][0]['message']);
        }
        $this->assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), 'Return code must be 201.');

        $formId = $response['form']['id'];
        $this->assertGreaterThan(0, $formId);
        $this->assertEquals($payload['name'], $response['form']['name']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertEquals($payload['isPublished'], $response['form']['isPublished']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertIsArray($response['form']['fields']);
        $this->assertCount(count($payload['fields']), $response['form']['fields']);
        for ($i = 0; $i < count($payload['fields']); ++$i) {
            $this->assertEquals($payload['fields'][$i]['label'], $response['form']['fields'][$i]['label']);
            $this->assertEquals($payload['fields'][$i]['alias'], $response['form']['fields'][$i]['alias']);
            $this->assertEquals($payload['fields'][$i]['type'], $response['form']['fields'][$i]['type']);
            $this->assertEquals($payload['fields'][$i]['leadField'], $response['form']['fields'][$i]['leadField']);
        }

        // Edit:
        $this->client->request('PATCH', "/api/forms/{$formId}/edit", ['name' => 'Form API renamed']);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());
        $this->assertSame($formId, $response['form']['id'], 'ID of the created form does not match with the edited one.');
        $this->assertEquals('Form API renamed', $response['form']['name']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertEquals($payload['isPublished'], $response['form']['isPublished']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertIsArray($response['form']['fields']);
        $this->assertCount(count($payload['fields']), $response['form']['fields']);
        for ($i = 0; $i < count($payload['fields']); ++$i) {
            $this->assertEquals($payload['fields'][$i]['label'], $response['form']['fields'][$i]['label']);
            $this->assertEquals($payload['fields'][$i]['alias'], $response['form']['fields'][$i]['alias']);
            $this->assertEquals($payload['fields'][$i]['type'], $response['form']['fields'][$i]['type']);
            $this->assertEquals($payload['fields'][$i]['leadField'], $response['form']['fields'][$i]['leadField']);
        }
    }

    public function testSingleFormWorkflow(): void
    {
        $payload    = self::TEST_PAYLOAD;
        $fieldCount = count($payload['fields']);
        $this->client->request(Request::METHOD_POST, '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = $response['form']['id'];

        $this->assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertGreaterThan(0, $formId);
        $this->assertEquals($payload['name'], $response['form']['name']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertNotEmpty($response['form']['cachedHtml']);
        $this->assertCount($fieldCount, $response['form']['fields']);
        $this->assertEquals($payload['fields'][0]['label'], $response['form']['fields'][0]['label']);
        $this->assertEquals($payload['fields'][0]['type'], $response['form']['fields'][0]['type']);
        $this->assertEquals($payload['fields'][0]['mappedObject'], $response['form']['fields'][0]['mappedObject']);
        $this->assertEquals($payload['fields'][0]['mappedField'], $response['form']['fields'][0]['mappedField']);
        $this->assertEquals(
            $payload['fields'][0]['mappedField'],
            $response['form']['fields'][0]['leadField']
        ); // @deprecated leadField was replaced by mappedField. Check for BC.
        $this->assertEquals($payload['fields'][0]['showLabel'], $response['form']['fields'][0]['showLabel']);
        $this->assertEquals($payload['fields'][0]['isRequired'], $response['form']['fields'][0]['isRequired']);
        $this->assertEquals($payload['fields'][1]['label'], $response['form']['fields'][1]['label']);
        $this->assertEquals($payload['fields'][1]['type'], $response['form']['fields'][1]['type']);
        $this->assertEquals('contact', $response['form']['fields'][1]['mappedObject']);
        $this->assertEquals('points', $response['form']['fields'][1]['mappedField']);
        $this->assertEquals(
            $payload['fields'][1]['leadField'],
            $response['form']['fields'][1]['leadField']
        ); // @deprecated leadField was replaced by mappedField. Check for BC.
        $this->assertTrue($response['form']['fields'][1]['showLabel']);
        $this->assertFalse($response['form']['fields'][1]['isRequired']);
        $this->assertEquals($payload['fields'][2]['label'], $response['form']['fields'][2]['label']);
        $this->assertEquals($payload['fields'][2]['type'], $response['form']['fields'][2]['type']);
        $this->assertEquals('contact', $response['form']['fields'][2]['mappedObject']);
        $this->assertEquals('company', $response['form']['fields'][2]['mappedField']);
        $this->assertEquals(
            $payload['fields'][2]['leadField'],
            $response['form']['fields'][2]['leadField']
        ); // @deprecated leadField was replaced by mappedField. Check for BC.
        $this->assertEquals($payload['fields'][3]['label'], $response['form']['fields'][3]['label']);
        $this->assertEquals($payload['fields'][3]['type'], $response['form']['fields'][3]['type']);
        $this->assertEquals('company', $response['form']['fields'][3]['mappedObject']);
        $this->assertEquals('companyphone', $response['form']['fields'][3]['mappedField']);
        $this->assertEquals(
            $payload['fields'][3]['leadField'],
            $response['form']['fields'][3]['leadField']
        ); // @deprecated leadField was replaced by mappedField. Check for BC.

        // Edit PATCH:
        $patchPayload = [
            'name'   => 'API form renamed',
            'fields' => [
                [
                    'label'        => 'State',
                    'type'         => 'select',
                    'alias'        => 'state',
                    'mappedObject' => 'contact',
                    'mappedField'  => 'state',
                    'parent'       => $response['form']['fields'][4]['id'],
                    'conditions'   => [
                        'expr'   => 'in',
                        'any'    => 1,
                        'values' => [],
                    ],
                    'properties'   => [
                        'syncList' => 1,
                        'multiple' => 0,
                    ],
                ],
            ],
        ];
        $this->client->request(Request::METHOD_PATCH, "/api/forms/{$formId}/edit", $patchPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $fieldCount     = $fieldCount + 1;

        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertSame($formId, $response['form']['id']);
        $this->assertEquals('API form renamed', $response['form']['name']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertCount($fieldCount, $response['form']['fields']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertNotEmpty($response['form']['cachedHtml']);

        // Edit PUT:
        $payload['description'] .= ' renamed';
        $payload['fields']      = []; // Set fields to an empty array as it would duplicate all fields.
        $payload['postAction']  = 'return'; // Must be present for PUT as all empty values are being cleared.
        $this->client->request(Request::METHOD_PUT, "/api/forms/{$formId}/edit", $payload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertSame($formId, $response['form']['id']);
        $this->assertEquals($payload['name'], $response['form']['name']);
        $this->assertEquals('Form created via API test renamed', $response['form']['description']);
        $this->assertCount($fieldCount, $response['form']['fields']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertNotEmpty($response['form']['cachedHtml']);

        // Get:
        $this->client->request(Request::METHOD_GET, "/api/forms/{$formId}");
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertSame($formId, $response['form']['id']);
        $this->assertEquals($payload['name'], $response['form']['name']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertCount($fieldCount, $response['form']['fields']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertNotEmpty($response['form']['cachedHtml']);

        // Submit the form:
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$formId}");
        $formCrawler = $crawler->filter('form[id=mauticform_apiform]');
        $this->assertSame(1, $formCrawler->count());
        $form = $formCrawler->form();
        $form->setValues([
            'mauticform[email]'       => 'john@doe.test',
            'mauticform[number]'      => '123',
            'mauticform[company]'     => 'Doe Corp',
            'mauticform[phone]'       => '+420444555666',
            'mauticform[country]'     => 'Czech Republic',
            'mauticform[state]'       => 'Plzeňský kraj',
            'mauticform[multiselect]' => ['two'],
        ]);
        $this->client->submit($form);

        // Ensure the submission was created properly.
        $submissions = $this->em->getRepository(Submission::class)->findAll();

        Assert::assertCount(1, $submissions);

        /** @var Submission $submission */
        $submission = $submissions[0];
        Assert::assertSame([
            '`email`'       => 'john@doe.test',
            '`number`'      => 123.0,
            '`company`'     => 'Doe Corp',
            '`phone`'       => '+420444555666',
            '`country`'     => 'Czech Republic',
            '`multiselect`' => 'two',
            '`state`'       => 'Plzeňský kraj',
        ], $submission->getResults());

        // A contact should be created by the submission.
        $contact = $submission->getLead();

        Assert::assertSame('john@doe.test', $contact->getEmail());
        Assert::assertSame('Czech Republic', $contact->getCountry());
        Assert::assertSame('Plzeňský kraj', $contact->getState());
        Assert::assertSame(123, $contact->getPoints());
        Assert::assertSame('Doe Corp', $contact->getCompany());

        $companies = $this->em->getRepository(Company::class)->findAll();

        Assert::assertCount(1, $companies);

        // A company should be created by the submission.
        /** @var Company $company */
        $company = $companies[0];
        Assert::assertSame('Doe Corp', $company->getName());
        Assert::assertSame('+420444555666', $company->getPhone());

        // The previous request changes user to anonymous. We have to configure API again.
        $this->setUpSymfony(
            [
                'api_enabled'                       => true,
                'api_enable_basic_auth'             => true,
                'create_custom_field_in_background' => false,
                'mailer_from_name'                  => 'Mautic',
                'db_driver'                         => 'pdo_mysql',
            ]
        );

        // Delete:
        $this->client->request(Request::METHOD_DELETE, "/api/forms/{$formId}/delete");
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertNull($response['form']['id']);
        $this->assertEquals($payload['name'], $response['form']['name']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertCount($fieldCount, $response['form']['fields']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertNotEmpty($response['form']['cachedHtml']);

        // Get (ensure that the form is gone):
        $this->client->request(Request::METHOD_GET, "/api/forms/{$formId}");
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);

        $this->assertSame(Response::HTTP_NOT_FOUND, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $this->assertSame(Response::HTTP_NOT_FOUND, $response['errors'][0]['code']);
    }

    public function testFormWithChangeTagsAction()
    {
        // Create tag:
        $tag1Payload = ['tag' => 'add this'];
        $tag2Payload = ['tag' => 'remove this'];

        $this->client->request('POST', '/api/tags/new', $tag1Payload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $tag1Id         = $response['tag']['id'];

        $this->client->request('POST', '/api/tags/new', $tag2Payload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $tag2Id         = $response['tag']['id'];

        $payload = [
            'name'        => 'Form API test',
            'formType'    => 'standalone',
            'isPublished' => true,
            'description' => 'Functional API test',
            'fields'      => [
                [
                    'label'     => 'lab',
                    'alias'     => 'email',
                    'type'      => 'text',
                    'leadField' => 'email',
                ],
            ],
            'actions' => [
                [
                    'name'        => 'Add tags to contact',
                    'description' => 'action description',
                    'type'        => 'lead.changetags',
                    'order'       => 1,
                    'properties'  => [
                        'add_tags'    => [$tag1Id],
                        'remove_tags' => [$tag2Id],
                    ],
                ],
            ],
        ];

        // Create form with lead.changetags action:
        $this->client->request('POST', '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);

        if (!empty($response['errors'][0])) {
            $this->fail($response['errors'][0]['code'].': '.$response['errors'][0]['message']);
        }
        $this->assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), 'Return code must be 201.');

        $formId = $response['form']['id'];
        $this->assertGreaterThan(0, $formId);
        $this->assertEquals($payload['name'], $response['form']['name']);
        $this->assertEquals($payload['formType'], $response['form']['formType']);
        $this->assertEquals($payload['isPublished'], $response['form']['isPublished']);
        $this->assertEquals($payload['description'], $response['form']['description']);
        $this->assertIsArray($response['form']['fields']);
        $this->assertCount(count($payload['fields']), $response['form']['fields']);
        for ($i = 0; $i < count($payload['fields']); ++$i) {
            $this->assertEquals($payload['fields'][$i]['label'], $response['form']['fields'][$i]['label']);
            $this->assertEquals($payload['fields'][$i]['alias'], $response['form']['fields'][$i]['alias']);
            $this->assertEquals($payload['fields'][$i]['type'], $response['form']['fields'][$i]['type']);
            $this->assertEquals($payload['fields'][$i]['leadField'], $response['form']['fields'][$i]['leadField']);
        }
        $this->assertIsArray($response['form']['actions']);
        $this->assertCount(count($payload['actions']), $response['form']['actions']);
        $this->assertEquals($payload['actions'][0]['name'], $response['form']['actions'][0]['name']);
        $this->assertEquals($payload['actions'][0]['description'], $response['form']['actions'][0]['description']);
        $this->assertEquals($payload['actions'][0]['type'], $response['form']['actions'][0]['type']);
        $this->assertEquals($payload['actions'][0]['order'], $response['form']['actions'][0]['order']);
        $this->assertIsArray($response['form']['actions'][0]['properties']['add_tags']);
        $this->assertIsArray($response['form']['actions'][0]['properties']['remove_tags']);
        $this->assertEquals($tag1Payload['tag'], $response['form']['actions'][0]['properties']['add_tags'][0]);
        $this->assertEquals($tag2Payload['tag'], $response['form']['actions'][0]['properties']['remove_tags'][0]);
    }
}
