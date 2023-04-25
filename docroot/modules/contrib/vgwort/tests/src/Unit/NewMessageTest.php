<?php

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\IdentificationCode;
use Drupal\vgwort\Api\MessageText;
use Drupal\vgwort\Api\NewMessage;
use Drupal\vgwort\Api\PersonParticipant;
use Drupal\vgwort\Api\Webrange;

/**
 * @coversDefaultClass \Drupal\search_api\Utility\FieldsHelper
 *
 * @group vgwort
 */
class NewMessageTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialisation(): void {
    $id = new IdentificationCode('12345678', 'ISNI');
    $id2 = new IdentificationCode('87654321', 'IPI');
    $participant = new PersonParticipant(435236412, 'Octavia', 'Butler', 'AUTHOR', [$id, $id2]);
    $participant2 = new PersonParticipant(4645643, 'The', 'Penguin', 'PUBLISHER', []);

    $webrange = new Webrange(['http://example.com/node/1', 'http://example.com/node/2']);
    $webrange2 = new Webrange(['http://example2.com/node/1']);

    $text = new MessageText('The title', 'The text');

    $new_message = new NewMessage('A-UUID-COUNTER', $text, [$participant, $participant2], [$webrange, $webrange2]);
    $expected_value = <<<JSON
{
    "distributionRight": false,
    "messagetext": {
        "lyric": false,
        "shorttext": "The title",
        "text": {
            "plainText": "VGhlIHRleHQ="
        }
    },
    "otherRightsOfPublicReproduction": false,
    "participants": [
        {
            "cardNumber": 435236412,
            "firstName": "Octavia",
            "identificationCodes": [
                {
                    "code": "12345678",
                    "codeType": "ISNI"
                },
                {
                    "code": "87654321",
                    "codeType": "IPI"
                }
            ],
            "surName": "Butler",
            "involvement": "AUTHOR"
        },
        {
            "cardNumber": 4645643,
            "firstName": "The",
            "surName": "Penguin",
            "involvement": "PUBLISHER"
        }
    ],
    "privateidentificationid": "A-UUID-COUNTER",
    "publicAccessRight": false,
    "reproductionRight": false,
    "rightsGrantedConfirmation": false,
    "webranges": [
        {
            "urls": [
                "http:\/\/example.com\/node\/1",
                "http:\/\/example.com\/node\/2"
            ]
        },
        {
            "urls": [
                "http:\/\/example2.com\/node\/1"
            ]
        }
    ],
    "withoutOwnParticipation": false
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($new_message));

    $new_message = new NewMessage('ANOTHER-UUID-COUNTER', $text, [$participant2], [$webrange2], TRUE, TRUE, TRUE, TRUE, TRUE, TRUE);
    $expected_value = <<<JSON
{
    "distributionRight": true,
    "messagetext": {
        "lyric": false,
        "shorttext": "The title",
        "text": {
            "plainText": "VGhlIHRleHQ="
        }
    },
    "otherRightsOfPublicReproduction": true,
    "participants": [
        {
            "cardNumber": 4645643,
            "firstName": "The",
            "surName": "Penguin",
            "involvement": "PUBLISHER"
        }
    ],
    "privateidentificationid": "ANOTHER-UUID-COUNTER",
    "publicAccessRight": true,
    "reproductionRight": true,
    "rightsGrantedConfirmation": true,
    "webranges": [
        {
            "urls": [
                "http:\/\/example2.com\/node\/1"
            ]
        }
    ],
    "withoutOwnParticipation": true
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($new_message));
  }

  public function testInvalidParticipant(): void {
    $this->expectError();
    $text = new MessageText('The title', 'The text');
    new NewMessage('A-UUID-COUNTER', $text, ['test'], []);
  }

  public function testInvalidWebrange(): void {
    $this->expectError();
    $text = new MessageText('The title', 'The text');
    new NewMessage('A-UUID-COUNTER', $text, [], ['test']);
  }

}
