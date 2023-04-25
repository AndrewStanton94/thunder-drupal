<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Assertion\Inspector;

/**
 * Represents a newMessage for VG Wort's REST API.
 *
 * @see https://tom.vgwort.de/api/external/swagger-ui/index.html#/message-external-rest-controller/newMessageUsingPOST_1
 */
class NewMessage implements \JsonSerializable {

  /**
   * Distribution right (§ 17 UrhG).
   *
   * @var bool
   *
   * @see https://www.gesetze-im-internet.de/urhg/__17.html
   */
  private bool $distributionRight;

  /**
   * The message text.
   *
   * @var \Drupal\vgwort\Api\MessageText
   */
  private MessageText $messageText;

  /**
   * Other Public Communication Rights (§§ 19, 20, 21, 22 UrhG).
   *
   * @var bool
   *
   * @see https://www.gesetze-im-internet.de/urhg/__19.html
   * @see https://www.gesetze-im-internet.de/urhg/__20.html
   * @see https://www.gesetze-im-internet.de/urhg/__21.html
   * @see https://www.gesetze-im-internet.de/urhg/__22.html
   */
  private bool $otherRightsOfPublicReproduction;

  /**
   * The originators / translators / agencies of the message.
   *
   * At least one author or translator must be specified. Both authors and
   * translators can be specified in a report.
   *
   * @var \Drupal\vgwort\Api\Participant[]
   */
  private array $participants;

  /**
   * Identification ID of the counter mark.
   *
   * Either the private counter ID identification code (in case of a VG WORT
   * counter id) or the publisher key.
   *
   * @var string
   */
  private string $privateidentificationid;

  /**
   * Right of public access (§ 19a UrhG).
   *
   * @var bool
   *
   * @see https://www.gesetze-im-internet.de/urhg/__19a.html
   */
  private bool $publicAccessRight;

  /**
   * Reproduction Rights (§ 16 UrhG).
   *
   * @var bool
   *
   * @see https://www.gesetze-im-internet.de/urhg/__16.html
   */
  private bool $reproductionRight;

  /**
   * Declaration of Granting of Rights.
   *
   * The right of reproduction (§ 16 UrhG), right of distribution (§ 17 UrhG),
   * right of public access (§ 19a UrhG) and the declaration of granting rights
   * must be confirmed.
   *
   * @var bool
   */
  private bool $rightsGrantedConfirmation;

  /**
   * Publication location(s) where the text can be found.
   *
   * @var \Drupal\vgwort\Api\Webrange[]
   */
  private array $webranges;

  /**
   * Indication of whether the publisher is involved in the work.
   *
   * @var bool
   */
  private bool $withoutOwnParticipation;

  /**
   * @param string $privateidentificationid
   *   Identification ID of the counter mark.
   * @param \Drupal\vgwort\Api\MessageText $messageText
   *   The message text.
   * @param \Drupal\vgwort\Api\Participant[] $participants
   *   The authors and publisher of the message.
   * @param \Drupal\vgwort\Api\Webrange[] $webranges
   *   Publication location(s) where the text can be found.
   * @param bool $distributionRight
   *   Distribution right (§ 17 UrhG).
   * @param bool $publicAccessRight
   *   Right of public access (§ 19a UrhG).
   * @param bool $reproductionRight
   *   Reproduction Rights (§ 16 UrhG).
   * @param bool $rightsGrantedConfirmation
   *   Declaration of Granting of Rights.
   * @param bool $otherRightsOfPublicReproduction
   *   Other Public Communication Rights (§§ 19, 20, 21, 22 UrhG).
   * @param bool $withoutOwnParticipation
   *   Indication of whether the publisher is involved in the work.
   */
  public function __construct(
    string $privateidentificationid,
    MessageText $messageText,
    array $participants,
    array $webranges,
    bool $distributionRight = FALSE,
    bool $publicAccessRight = FALSE,
    bool $reproductionRight = FALSE,
    bool $rightsGrantedConfirmation = FALSE,
    bool $otherRightsOfPublicReproduction = FALSE,
    bool $withoutOwnParticipation = FALSE
  ) {
    assert(Inspector::assertAllObjects($participants, Participant::class));
    assert(Inspector::assertAllObjects($webranges, Webrange::class));

    $this->distributionRight = $distributionRight;
    $this->messageText = $messageText;
    $this->otherRightsOfPublicReproduction = $otherRightsOfPublicReproduction;
    $this->participants = $participants;
    $this->privateidentificationid = $privateidentificationid;
    $this->publicAccessRight = $publicAccessRight;
    $this->reproductionRight = $reproductionRight;
    $this->rightsGrantedConfirmation = $rightsGrantedConfirmation;
    $this->webranges = $webranges;
    $this->withoutOwnParticipation = $withoutOwnParticipation;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'distributionRight' => $this->distributionRight,
      'messagetext' => $this->messageText,
      'otherRightsOfPublicReproduction' => $this->otherRightsOfPublicReproduction,
      'participants' => $this->participants,
      'privateidentificationid' => $this->privateidentificationid,
      'publicAccessRight' => $this->publicAccessRight,
      'reproductionRight' => $this->reproductionRight,
      'rightsGrantedConfirmation' => $this->rightsGrantedConfirmation,
      'webranges' => $this->webranges,
      'withoutOwnParticipation' => $this->withoutOwnParticipation,
    ];
  }

}
