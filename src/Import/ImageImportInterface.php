<?php

/**
 * ImageImportInterface.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfImage
 * @author    jmleroux <jmleroux.pro@gmail.com>
 * @copyright 2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image\Import;

/**
 * Com\Tecnick\Pdf\Image\Import\Jpeg
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfImage
 * @author    jmleroux <jmleroux.pro@gmail.com>
 * @copyright 2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 */
interface ImageImportInterface
{
    /**
     * Extract data from an image.
     *
     * @param array{
     *          'bits': int,
     *          'channels': int,
     *          'colspace': string,
     *          'data': string,
     *          'exturl': bool,
     *          'file': string,
     *          'filter': string,
     *          'height': int,
     *          'icc': string,
     *          'ismask': bool,
     *          'key': string,
     *          'mapto': int,
     *          'native': bool,
     *          'obj': int,
     *          'obj_alt': int,
     *          'obj_icc': int,
     *          'obj_pal': int,
     *          'pal': string,
     *          'parms': string,
     *          'raw': string,
     *          'recode': bool,
     *          'recoded': bool,
     *          'splitalpha': bool,
     *          'trns': array<int, int>,
     *          'type': int,
     *          'width': int,
     *        } $data Image raw data.
     *
     * @return array{
     *          'bits': int,
     *          'channels': int,
     *          'colspace': string,
     *          'data': string,
     *          'exturl': bool,
     *          'file': string,
     *          'filter': string,
     *          'height': int,
     *          'icc': string,
     *          'ismask': bool,
     *          'key': string,
     *          'mapto': int,
     *          'native': bool,
     *          'obj': int,
     *          'obj_alt': int,
     *          'obj_icc': int,
     *          'obj_pal': int,
     *          'pal': string,
     *          'parms': string,
     *          'raw': string,
     *          'recode': bool,
     *          'recoded': bool,
     *          'splitalpha': bool,
     *          'trns': array<int, int>,
     *          'type': int,
     *          'width': int,
     *        } Image raw data array.
     */
    public function getData(array $data): array;
}
