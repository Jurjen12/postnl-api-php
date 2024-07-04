<?php

/**
 * The MIT License (MIT).
 *
 * Copyright (c) 2017-2023 Michael Dekker (https://github.com/firstred)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <git@michaeldekker.nl>
 * @copyright 2017-2023 Michael Dekker
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

declare(strict_types=1);

namespace Firstred\PostNL\Entity\Response;

use Firstred\PostNL\Attribute\SerializableProperty;
use Firstred\PostNL\Entity\AbstractEntity;
use Firstred\PostNL\Entity\Label;
use Firstred\PostNL\Entity\Warning;

/**
 * @since 1.0.0
 */
class ResponseShipment extends AbstractEntity
{
    /** @var string|null $Barcode */
    #[SerializableProperty(type: 'string')]
    protected ?string $Barcode = null;

    /** @var string|null $DownPartnerBarcode */
    #[SerializableProperty(type: 'string')]
    protected ?string $DownPartnerBarcode = null;

    /** @var string|null $DownPartnerID */
    #[SerializableProperty(type: 'string')]
    protected ?string $DownPartnerID = null;

    /** @var string|null $DownPartnerLocation */
    #[SerializableProperty(type: 'string')]
    protected ?string $DownPartnerLocation = null;

    /** @var Label[]|null $Labels */
    #[SerializableProperty(type: Label::class, isArray: true)]
    protected ?array $Labels = null;

    /** @var string|null $ProductCodeDelivery */
    #[SerializableProperty(type: 'string')]
    protected ?string $ProductCodeDelivery = null;

    /** @var Warning[]|null $Warnings */
    #[SerializableProperty(type: Warning::class, isArray: true)]
    protected ?array $Warnings = null;

    /** @var string|null $CodingText */
    #[SerializableProperty(type: 'string')]
    protected ?string $CodingText = null;

    /**
     * @param string|null $Barcode
     * @param string|null $ProductCodeDelivery
     * @param array|null  $Labels
     * @param string|null $DownPartnerBarcode
     * @param string|null $DownPartnerID
     * @param string|null $DownPartnerLocation
     * @param array|null  $Warnings
     */
    public function __construct(
        ?string $Barcode = null,
        ?string $ProductCodeDelivery = null,
        /* @param Label[]|null $Labels */
        ?array $Labels = null,
        ?string $DownPartnerBarcode = null,
        ?string $DownPartnerID = null,
        ?string $DownPartnerLocation = null,
        /* @param Warning[]|null $Warnings */
        ?array $Warnings = null,
        ?string $CodingText = null
    ) {
        parent::__construct();

        $this->setBarcode(Barcode: $Barcode);
        $this->setProductCodeDelivery(ProductCodeDelivery: $ProductCodeDelivery);
        $this->setDownPartnerBarcode(DownPartnerBarcode: $DownPartnerBarcode);
        $this->setDownPartnerId(DownPartnerID: $DownPartnerID);
        $this->setDownPartnerLocation(DownPartnerLocation: $DownPartnerLocation);
        $this->setLabels(Labels: $Labels);
        $this->setWarnings(Warnings: $Warnings);
        $this->setCodingText(CodingText: $CodingText);
    }

    /**
     * @return string|null
     */
    public function getBarcode(): ?string
    {
        return $this->Barcode;
    }

    /**
     * @param string|null $Barcode
     *
     * @return static
     */
    public function setBarcode(?string $Barcode): static
    {
        $this->Barcode = $Barcode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDownPartnerBarcode(): ?string
    {
        return $this->DownPartnerBarcode;
    }

    /**
     * @param string|null $DownPartnerBarcode
     *
     * @return static
     */
    public function setDownPartnerBarcode(?string $DownPartnerBarcode): static
    {
        $this->DownPartnerBarcode = $DownPartnerBarcode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDownPartnerID(): ?string
    {
        return $this->DownPartnerID;
    }

    /**
     * @param string|null $DownPartnerID
     *
     * @return static
     */
    public function setDownPartnerID(?string $DownPartnerID): static
    {
        $this->DownPartnerID = $DownPartnerID;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDownPartnerLocation(): ?string
    {
        return $this->DownPartnerLocation;
    }

    /**
     * @param string|null $DownPartnerLocation
     *
     * @return static
     */
    public function setDownPartnerLocation(?string $DownPartnerLocation): static
    {
        $this->DownPartnerLocation = $DownPartnerLocation;

        return $this;
    }

    /**
     * @return Label[]|null
     */
    public function getLabels(): ?array
    {
        return $this->Labels;
    }

    /**
     * @param Label[]|null $Labels
     *
     * @return static
     */
    public function setLabels(?array $Labels): static
    {
        $this->Labels = $Labels;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getProductCodeDelivery(): ?string
    {
        return $this->ProductCodeDelivery;
    }

    /**
     * @param string|null $ProductCodeDelivery
     *
     * @return static
     */
    public function setProductCodeDelivery(?string $ProductCodeDelivery): static
    {
        $this->ProductCodeDelivery = $ProductCodeDelivery;

        return $this;
    }

    /**
     * @return Warning[]|null
     */
    public function getWarnings(): ?array
    {
        return $this->Warnings;
    }

    /**
     * @param Warning[]|null $Warnings
     *
     * @return static
     */
    public function setWarnings(?array $Warnings): static
    {
        $this->Warnings = $Warnings;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCodingText(): ?string
    {
        return $this->CodingText;
    }

    /**
     * @param string|null $CodingText
     *
     * @return static
     */
    public function setCodingText(?string $CodingText): static
    {
        $this->CodingText = $CodingText;

        return $this;
    }
}
