<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Context\CurrentContext;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer
 * @group jsonapi
 */
class JsonApiDocumentTopLevelNormalizerTest extends UnitTestCase {

  /**
   * The normalizer under test.
   *
   * @var \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $link_manager = $this->prophesize(LinkManager::class);
    $current_context_manager = $this->prophesize(CurrentContext::class);

    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $self = $this;
    $uuid_to_id = [
      '76dd5c18-ea1b-4150-9e75-b21958a2b836' => 1,
      'fcce1b61-258e-4054-ae36-244d25a9e04c' => 2,
    ];
    $entity_storage->loadByProperties(Argument::type('array'))
      ->will(function ($args) use ($self, $uuid_to_id) {
        $result = [];
        foreach ($args[0]['uuid'] as $uuid) {
          $entity = $self->prophesize(EntityInterface::class);
          $entity->uuid()->willReturn($uuid);
          $entity->id()->willReturn($uuid_to_id[$uuid]);
          $result[$uuid] = $entity->reveal();
        }
        return $result;
      });
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('node')
      ->willReturn($entity_storage->reveal());

    $current_route = $this->prophesize(Route::class);
    $current_route->getDefault('_on_relationship')->willReturn(FALSE);

    $current_context_manager->isOnRelationship()->willReturn(FALSE);

    $this->normalizer = new JsonApiDocumentTopLevelNormalizer(
      $link_manager->reveal(),
      $current_context_manager->reveal(),
      $entity_type_manager->reveal()
    );

    $serializer = $this->prophesize(DenormalizerInterface::class);
    $serializer->willImplement(SerializerInterface::class);
    $serializer->denormalize(
      Argument::type('array'),
      Argument::type('string'),
      Argument::type('string'),
      Argument::type('array')
    )->willReturnArgument(0);

    $this->normalizer->setSerializer($serializer->reveal());
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($input, $expected) {
    $context = [
      'resource_type' => new ResourceType($this->randomMachineName(), $this->randomMachineName(), FieldableEntityInterface::class),
    ];
    $denormalized = $this->normalizer->denormalize($input, NULL, 'api_json', $context);
    $this->assertSame($expected, $denormalized);
  }

  /**
   * Data provider for the denormalize test.
   *
   * @return array
   *   The data for the test method.
   */
  public function denormalizeProvider() {
    return [
      [
        [
          'data' => [
            'type' => 'lorem',
            'id' => 'e1a613f6-f2b9-4e17-9d33-727eb6509d8b',
            'attributes' => ['title' => 'dummy_title'],
          ],
        ],
        ['title' => 'dummy_title'],
      ],
      [
        [
          'data' => [
            'type' => 'lorem',
            'id' => '0676d1bf-55b3-4bbc-9fbc-3df10f4599d5',
            'relationships' => ['field_dummy' => ['data' => ['type' => 'node', 'id' => '76dd5c18-ea1b-4150-9e75-b21958a2b836']]],
          ],
        ],
        ['field_dummy' => [1]],
      ],
      [
        [
          'data' => [
            'type' => 'lorem',
            'id' => '535ba297-8d79-4fc1-b0d6-dc2f047765a1',
            'relationships' => ['field_dummy' => ['data' => [['type' => 'node', 'id' => '76dd5c18-ea1b-4150-9e75-b21958a2b836'], ['type' => 'node', 'id' => 'fcce1b61-258e-4054-ae36-244d25a9e04c']]]],
          ],
        ],
        ['field_dummy' => [1, 2]],
      ],
    ];
  }

}