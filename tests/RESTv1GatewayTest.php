<?php
/**
 * Copyright 2016 Tom Walder
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Tests for REST API v1 Gateway
 *
 * @todo Consider storing the request and response payloads as JSON files
 *
 * @author Tom Walder <tom@docnet.nu>
 */
class RESTv1GatewayTest extends \PHPUnit_Framework_TestCase
{

    const TEST_PROJECT = 'DatasetTest';

    private $str_expected_url = null;

    private $arr_expected_payload = null;

    /**
     * Prepare and return a fake Guzzle HTTP client, so that we can test and simulate requests/responses
     *
     * @param $str_expected_url
     * @param null $arr_expected_payload
     * @param null $obj_response
     * @return FakeGuzzleClient
     */
    private function initTestHttpClient($str_expected_url, $arr_expected_payload = null, $obj_response = null)
    {
        $this->str_expected_url = $str_expected_url;
        $this->arr_expected_payload = $arr_expected_payload;
        return new FakeGuzzleClient($obj_response);
    }

    private function initTestGateway()
    {
        return $this->getMockBuilder('\\GDS\\Gateway\\RESTv1')->setMethods(['initHttpClient'])->setConstructorArgs([self::TEST_PROJECT])->getMock();
    }

    /**
     * Validate URL and Payload
     *
     * @param FakeGuzzleClient $obj_http
     */
    private function validateHttpClient(\FakeGuzzleClient $obj_http)
    {
        $this->assertEquals($this->str_expected_url, $obj_http->getPostedUrl());
        if(null !== $this->arr_expected_payload) {
           $this->assertEquals($this->arr_expected_payload, $obj_http->getPostedParams());
        }
    }

    /**
     * Test begin transaction
     */
    public function testTransaction()
    {
        $str_txn_ref = 'txn-string-here';
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:beginTransaction', [], ['transaction' => $str_txn_ref]);
        /** @var \GDS\Gateway\RESTv1 $obj_gateway */
        $obj_gateway = $this->initTestGateway()->setHttpClient($obj_http);

        $str_txn = $obj_gateway->beginTransaction();

        $this->assertEquals($str_txn_ref, $str_txn);
        $this->validateHttpClient($obj_http);
    }

    /**
     * Test basic entity delete
     */
    public function testDelete()
    {
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:commit', ['json' => (object)[
            'mode' => 'NON_TRANSACTIONAL',
            'mutations' => [
                (object)[
                    'delete' => (object)[
                        'path' => [
                            (object)[
                                'kind' => 'Test',
                                'id' => '123456789'
                            ]
                        ],
                        'partitionId' => (object)[
                            'projectId' => self::TEST_PROJECT
                        ]
                    ]
                ]
            ]
        ]]);
        $obj_gateway = $this->initTestGateway()->setHttpClient($obj_http);

        $obj_store = new \GDS\Store('Test', $obj_gateway);
        $obj_entity = (new GDS\Entity())->setKeyId('123456789');
        $obj_store->delete([$obj_entity]);

        $this->validateHttpClient($obj_http);
    }

    /**
     * Test basic entity upsert
     */
    public function testBasicUpsert()
    {
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:commit', ['json' => (object)[
            'mode' => 'NON_TRANSACTIONAL',
            'mutations' => [
                (object)[
                    'upsert' => (object)[
                        'key' => (object)[
                            'path' => [
                                (object)[
                                    'kind' => 'Test',
                                    'id' => '123456789'
                                ]
                            ],
                            'partitionId' => (object)[
                                'projectId' => self::TEST_PROJECT
                            ]
                        ],
                        'properties' => (object)[
                            'name' => (object)[
                                'excludeFromIndexes' => false,
                                'stringValue' => 'Tom'
                            ]
                        ]
                    ]
                ]
            ]
        ]]);
        $obj_gateway = $this->initTestGateway()->setHttpClient($obj_http);

        $obj_store = new \GDS\Store('Test', $obj_gateway);
        $obj_entity = new GDS\Entity();
        $obj_entity->setKeyId('123456789');
        $obj_entity->name = 'Tom';
        $obj_store->upsert($obj_entity);

        $this->validateHttpClient($obj_http);
    }

    /**
     * Test transactional entity upsert
     */
    public function testTxnUpsert()
    {

        // First begin the transaction
        $str_txn_ref = 'ghei34g498jhegijv0894hiwgerhiugjreiugh';
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:beginTransaction', [], ['transaction' => $str_txn_ref]);
        /** @var \GDS\Gateway\RESTv1 $obj_gateway */
        $obj_gateway = $this->initTestGateway()->setHttpClient($obj_http);
        $obj_store = new \GDS\Store('Test', $obj_gateway);
        $obj_store->beginTransaction();
        $this->validateHttpClient($obj_http);


        // Now set up the transactional upsert
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:commit', ['json' => (object)[
            'mode' => 'TRANSACTIONAL',
            'transaction' => $str_txn_ref,
            'mutations' => [
                (object)[
                    'upsert' => (object)[
                        'key' => (object)[
                            'path' => [
                                (object)[
                                    'kind' => 'Test',
                                    'id' => '123456789'
                                ]
                            ],
                            'partitionId' => (object)[
                                'projectId' => self::TEST_PROJECT
                            ]
                        ],
                        'properties' => (object)[
                            'name' => (object)[
                                'excludeFromIndexes' => false,
                                'stringValue' => 'Tom'
                            ]
                        ]
                    ]
                ]
            ]
        ]]);
        $obj_gateway->setHttpClient($obj_http);

        // Do the upsert
        $obj_entity = new GDS\Entity();
        $obj_entity->setKeyId('123456789');
        $obj_entity->name = 'Tom';
        $obj_store->upsert($obj_entity);

        // Test the final output
        $this->validateHttpClient($obj_http);
    }

    /**
     * Test basic entity insert
     */
    public function testBasicInsert()
    {
        $int_new_id = mt_rand(100000, 999999);
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:commit', ['json' => (object)[
            'mode' => 'NON_TRANSACTIONAL',
            'mutations' => [
                (object)[
                    'insert' => (object)[
                        'key' => (object)[
                            'path' => [
                                (object)[
                                    'kind' => 'Test'
                                ]
                            ],
                            'partitionId' => (object)[
                                'projectId' => self::TEST_PROJECT
                            ]
                        ],
                        'properties' => (object)[
                            'name' => (object)[
                                'excludeFromIndexes' => false,
                                'stringValue' => 'Tom'
                            ]
                        ]
                    ]
                ]
            ]
        ]], [
            'mutationResults' => [
                (object)[
                    'key' => (object)[
                        'path' => [
                            (object)[
                                'kind' => 'Test',
                                'id' => $int_new_id
                            ]
                        ],
                        'partitionId' => (object)[
                            'projectId' => self::TEST_PROJECT
                        ]
                    ],
                    'version' => '123'
                ]
            ]
        ]);
        $obj_gateway = $this->initTestGateway()->setHttpClient($obj_http);

        $obj_store = new \GDS\Store('Test', $obj_gateway);
        $obj_entity = new GDS\Entity();
        $obj_entity->name = 'Tom';
        $obj_store->upsert($obj_entity);

        $this->validateHttpClient($obj_http);

        $this->assertEquals($int_new_id, $obj_entity->getKeyId());
    }


    /**
     * Test fetch by single ID
     */
    public function testFetchById()
    {
        $str_id = '1263751723';
        $obj_http = $this->initTestHttpClient('https://datastore.googleapis.com/v1/projects/DatasetTest:lookup', ['json' => (object)[
            'keys' => [
                (object)[
                    'path' => [
                        (object)[
                            'kind' => 'Test',
                            'id' => $str_id
                        ]
                    ],
                    'partitionId' => (object)[
                        'projectId' => self::TEST_PROJECT
                    ]
                ]
            ]
        ]], [
            'found' => [
                (object)[
                    'entity' => (object)[
                        'key' => (object)[
                            'path' => [
                                (object)[
                                    'kind' => 'Test',
                                    'id' => $str_id
                                ]
                            ]
                        ],
                        'properties' => (object)[
                            'name' => (object)[
                                'excludeFromIndexes' => false,
                                'stringValue' => 'Tom'
                            ],
                            'age' => (object)[
                                'excludeFromIndexes' => false,
                                'integerValue' => 37
                            ],
                            'dob' => (object)[
                                'excludeFromIndexes' => false,
                                'timestampValue' => "2014-10-02T15:01:23.045123456Z"
                            ],
                            'likes' => (object)[
                                'excludeFromIndexes' => false,
                                'arrayValue' => (object)[
                                    'values' => [
                                        (object)[
                                            'stringValue' => 'Beer'
                                        ],
                                        (object)[
                                            'stringValue' => 'Cycling'
                                        ],
                                        (object)[
                                            'stringValue' => 'PHP'
                                        ]
                                    ]
                                ]
                            ],
                            'weight' => (object)[
                                'excludeFromIndexes' => false,
                                'doubleValue' => 85.99
                            ],
                            'author' => (object)[
                                'excludeFromIndexes' => false,
                                'booleanValue' => true
                            ],
                            'chickens' => (object)[
                                'excludeFromIndexes' => false,
                                'nullValue' => null
                            ],
                            'lives' => (object)[
                                'excludeFromIndexes' => false,
                                'geoPointValue' => (object)[
                                    'latitude' => 1.23,
                                    'longitude' => 4.56
                                ]
                            ],

                        ]
                    ],
                    'version' => '123',
                    'cursor' => 'gfuh37f86gyu23'

                ]
            ]
        ]);
        $obj_gateway = $this->initTestGateway()->setHttpClient($obj_http);

        $obj_store = new \GDS\Store('Test', $obj_gateway);
        $obj_entity = $obj_store->fetchById($str_id);

        $this->assertInstanceOf('\\GDS\\Entity', $obj_entity);
        $this->assertEquals($str_id, $obj_entity->getKeyId());
        $this->assertEquals('Tom', $obj_entity->name);
        $this->assertEquals(37, $obj_entity->age);
        $this->assertEquals('2014-10-02 15:01:23', $obj_entity->dob);
        $this->assertTrue(is_array($obj_entity->likes));
        $this->assertEquals(['Beer', 'Cycling', 'PHP'], $obj_entity->likes);
        $this->assertEquals(85.99, $obj_entity->weight);
        $this->assertInstanceOf('\\GDS\\Property\\Geopoint', $obj_entity->lives);
        $this->assertEquals(1.23, $obj_entity->lives->getLatitude());
        $this->assertEquals(4.56, $obj_entity->lives->getLongitude());
        $this->assertTrue($obj_entity->author);
        $this->assertNull($obj_entity->chickens);

        $this->validateHttpClient($obj_http);
    }
}