<?php

declare(strict_types=1);

/**
 * PngChunkParsingHarness.php
 *
 * @since     2026-05-21
 * @category  Library
 * @package   PdfImage
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Test;

/**
 * Png subclass exposing the protected chunk parsing methods to the tests
 *
 * @since     2026-05-21
 * @category  Library
 * @package   PdfImage
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * @phpstan-import-type ImageBaseData from \Com\Tecnick\Pdf\Image\Import
 */
class PngChunkParsingHarness extends \Com\Tecnick\Pdf\Image\Import\Png
{
    /**
     * @param ImageBaseData $data
     *
     * @return ImageBaseData
     */
    public function callGetTrnsChunk(array $data, int &$offset, int $len): array
    {
        return $this->getTrnsChunk($data, $offset, $len);
    }

    /**
     * @param ImageBaseData $data
     *
     * @return ImageBaseData
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \RangeException
     */
    public function callGetIccpChunk(\Com\Tecnick\File\Byte $byte, array $data, int &$offset, int $len): array
    {
        return $this->getIccpChunk($byte, $data, $offset, $len);
    }
}
