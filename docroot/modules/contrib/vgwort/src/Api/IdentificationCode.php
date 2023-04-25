<?php

namespace Drupal\vgwort\Api;

/**
 * Identification codes for authors.
 */
class IdentificationCode implements \JsonSerializable {

  private const CODE_TYPE_VALUES = ['ISNI', 'ORCID', 'GNDID', 'IPI'];

  /**
   * The identification code.
   *
   * @var string
   */
  private string $code;

  /**
   * ID type (ISNI, ORCID, GNDID, IPI).
   *
   * @var string
   */
  private string $codeType;

  /**
   * @param string $code
   *   The identification code.
   * @param string $codeType
   *   ID type (ISNI, ORCID, GNDID, IPI).
   */
  public function __construct(string $code, string $codeType) {
    if (!in_array($codeType, self::CODE_TYPE_VALUES, TRUE)) {
      throw new \InvalidArgumentException(sprintf("'%s' is not a valid code type.", $codeType));
    }
    $this->code = $code;
    $this->codeType = $codeType;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'code' => $this->code,
      'codeType' => $this->codeType,
    ];
  }

}
