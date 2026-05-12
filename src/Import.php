<?php

declare(strict_types=1);

/**
 * Import.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfImage
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image;

use Com\Tecnick\Pdf\Image\Exception as ImageException;
use Com\Tecnick\Pdf\Image\Import\ImageImportInterface;

/**
 * Com\Tecnick\Pdf\Image\Import
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfImage
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * @phpstan-type ImageBaseData array{
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
 *        }
 *
 * @phpstan-type ImageRawData array{
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
 *          'mask'?: ImageBaseData,
 *          'native': bool,
 *          'obj': int,
 *          'obj_alt': int,
 *          'obj_icc': int,
 *          'obj_pal': int,
 *          'out'?: true,
 *          'pal': string,
 *          'parms': string,
 *          'plain'?: ImageBaseData,
 *          'raw': string,
 *          'recode': bool,
 *          'recoded': bool,
 *          'splitalpha': bool,
 *          'trns': array<int, int>,
 *          'type': int,
 *          'width': int,
 *      }
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class Import extends \Com\Tecnick\Pdf\Image\Output
{
    /**
     * Image index.
     * Count the number of added images.
     */
    protected int $iid = 0;

    /**
     * Native image types and associated importing class.
     * (Image types for which we have an import method).
     *
     * @var array<int, string>
     */
    private const NATIVE = [
        IMAGETYPE_PNG => 'Png',
        IMAGETYPE_JPEG => 'Jpeg',
    ];

    /**
     * Lossless image types.
     *
     * @var array<int>
     */
    protected const LOSSLESS = [
        IMAGETYPE_GIF, // 1
        IMAGETYPE_PNG, // 3
        IMAGETYPE_PSD, // 5
        IMAGETYPE_BMP, // 6
        IMAGETYPE_WBMP, // 15
        IMAGETYPE_XBM, // 16
        IMAGETYPE_TIFF_II, // 7
        IMAGETYPE_TIFF_MM, // 8
        IMAGETYPE_IFF, // 14
        IMAGETYPE_SWC, // 13
        IMAGETYPE_ICO, // 17
    ];

    /**
     * Map number of channels with color space name.
     *
     * @var array<int, string>
     */
    protected const COLSPACEMAP = [
        1 => 'DeviceGray',
        3 => 'DeviceRGB',
        4 => 'DeviceCMYK',
    ];

    /**
     * Add a new image.
     *
     * @param string          $image    Image file name, URL or a '@' character followed by the image data string.
     *                                  To link an image without embedding it on the document, set an asterisk
     *                                  character before the URL (i.e.: '*http://www.example.com/image.jpg').
     * @param ?int            $width    New width in pixels or null to keep the original value.
     * @param ?int            $height   New height in pixels or null to keep the original value.
     * @param bool            $ismask   True if the image is a transparency mask.
     * @param int             $quality  Quality for JPEG files (0 = max compression; 100 = best quality, bigger file).
     * @param bool            $defprint Indicate if the image is the default
     *                                  for printing when used as alternative image.
     * @param array<int, int> $altimgs  Arrays of alternate image keys.
     *
     * @return int Image ID.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image cannot be added.
     * @throws \Com\Tecnick\File\Exception If reading image content fails.
     */
    public function add(
        string $image,
        ?int $width = null,
        ?int $height = null,
        bool $ismask = false,
        int $quality = 100,
        bool $defprint = false,
        array $altimgs = [],
    ): int {
        $data = $this->import($image, $width, $height, $ismask, $quality);
        ++$this->iid;
        $this->image[$this->iid] = [
            'iid' => $this->iid,
            'key' => $data['key'],
            'width' => $data['width'],
            'height' => $data['height'],
            'defprint' => $defprint,
            'altimgs' => $altimgs,
        ];
        return $this->iid;
    }

    /**
     * Get the Image key used for caching.
     *
     * @param string $image   Image file name or content.
     * @param int    $width   Width in pixels.
     * @param int    $height  Height in pixels.
     * @param int    $quality Quality for JPEG files.
     */
    public function getKey(string $image, int $width = 0, int $height = 0, int $quality = 100): string
    {
        return \strtr(\rtrim(\base64_encode(\pack('H*', \md5($image . $width . $height . $quality))), '='), '+/', '-_');
    }

    /**
     * Get an imported image by key.
     *
     * @param string $key Image key.
     *
     * @return ImageRawData Image raw data array.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the key is not found.
     */
    public function getImageDataByKey(string $key): array
    {
        $cache = $this->cache[$key] ?? null;
        if ($cache === null) {
            throw new ImageException('Unknownn key');
        }

        return $cache;
    }

    /**
     * Import the original image raw data.
     *
     * @param string $image   Image file name, URL or a '@' character followed by the image data string.
     *                        To link an image without embedding it on the document, set an asterisk
     *                        character before the URL (i.e.: '*http://www.example.com/image.jpg').
     * @param ?int   $width   New width in pixels or null to keep the original value.
     * @param ?int   $height  New height in pixels or null to keep the original value.
     * @param bool   $ismask  True if the image is a transparency mask.
     * @param int    $quality Quality for JPEG files (0 = max compression; 100 = best quality, bigger file).
     *
     * @return ImageRawData Image raw data array
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image cannot be imported.
     * @throws \Com\Tecnick\File\Exception If reading image content fails.
     */
    protected function import(
        string $image,
        ?int $width = null,
        ?int $height = null,
        bool $ismask = false,
        int $quality = 100,
    ): array {
        $quality = \max(0, \min(100, $quality));
        $imgkey = $this->getKey($image, (int) $width, (int) $height, $quality);

        if (isset($this->cache[$imgkey])) {
            return $this->cache[$imgkey];
        }

        $data = $this->getRawData($image);
        $data['key'] = $imgkey;

        if ($width === null) {
            $width = $data['width'];
        }

        $width = \max(0, (int) $width);

        if ($height === null) {
            $height = $data['height'];
        }

        $height = \max(0, (int) $height);

        if (!$data['native'] || $width !== $data['width'] || $height !== $data['height']) {
            $data = $this->getResizedRawData($this->getBaseData($data), $width, $height, true, $quality);
        }

        $data = $this->getData($data, $width, $height, $quality);

        $basedata = $this->getBaseData($data);

        if ($ismask) {
            $data['mask'] = $basedata;
        } elseif ($data['splitalpha']) {
            // create 2 separate images: plain + mask
            $plain = $this->getResizedRawData($basedata, $width, $height, false, $quality);
            $plain = $this->getData($plain, $width, $height, $quality);
            $plainbase = $this->getBaseData($plain);
            $data['plain'] = $plainbase;

            $mask = $this->getAlphaChannelRawData($basedata);
            $mask = $this->getData($mask, $width, $height, $quality);
            $maskbase = $this->getBaseData($mask);
            $mask = $maskbase;
            $mask['colspace'] = 'DeviceGray';
            $data['mask'] = $mask;
        }

        // store data in cache
        $this->cache[$imgkey] = $data;
        return $data;
    }

    /**
     * Extract the relevant data from the image.
     *
     * @param ImageRawData $data    Image raw data.
     * @param int          $width   Width in pixels.
     * @param int          $height  Height in pixels.
     * @param int          $quality Quality for JPEG files.
     *
     * @return ImageRawData Image raw data array.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image cannot be imported.
     */
    protected function getData(array $data, int $width, int $height, int $quality): array
    {
        if (!$data['native']) {
            throw new ImageException('Unable to import image');
        }

        $imageImport = $this->createImportImage($data);
        $data = $imageImport->getData($this->getBaseData($data));

        if ($data['recode']) {
            // re-encode the image as it was not possible to decode it
            $data = $this->getResizedRawData($this->getBaseData($data), $width, $height, true, $quality);
            $data = $imageImport->getData($this->getBaseData($data));
        }

        return $data;
    }

    /**
     * @param array{'type': int}|ImageRawData $data Image raw data.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image type is unknown.
     */
    private function createImportImage(array $data): ImageImportInterface
    {
        if (!isset(self::NATIVE[$data['type']])) {
            throw new ImageException('Unable to import image: unknown type');
        }

        return match (self::NATIVE[$data['type']]) {
            'Png' => new \Com\Tecnick\Pdf\Image\Import\Png(),
            'Jpeg' => new \Com\Tecnick\Pdf\Image\Import\Jpeg(),
            default => throw new ImageException('Unable to import image: unknown type'),
        };
    }

    /**
     * Normalize raw image data to its base shape.
     *
     * @param ImageRawData $data Image raw data.
     *
     * @return ImageBaseData Image base data.
     */
    private function getBaseData(array $data): array
    {
        return [
            'bits' => $data['bits'],
            'channels' => $data['channels'],
            'colspace' => $data['colspace'],
            'data' => $data['data'],
            'exturl' => $data['exturl'],
            'file' => $data['file'],
            'filter' => $data['filter'],
            'height' => $data['height'],
            'icc' => $data['icc'],
            'ismask' => $data['ismask'],
            'key' => $data['key'],
            'mapto' => $data['mapto'],
            'native' => $data['native'],
            'obj' => $data['obj'],
            'obj_alt' => $data['obj_alt'],
            'obj_icc' => $data['obj_icc'],
            'obj_pal' => $data['obj_pal'],
            'pal' => $data['pal'],
            'parms' => $data['parms'],
            'raw' => $data['raw'],
            'recode' => $data['recode'],
            'recoded' => $data['recoded'],
            'splitalpha' => $data['splitalpha'],
            'trns' => $data['trns'],
            'type' => $data['type'],
            'width' => $data['width'],
        ];
    }

    /**
     * Get the original image raw data.
     *
     * @param string $image Image file name, URL or a '@' character followed by the image data string.
     *                      To link an image without embedding it on the document, set an asterisk character
     *                      before the URL (i.e.: '*http://www.example.com/image.jpg').
     *
     * @return ImageRawData Image data array.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image cannot be read.
     * @throws \Com\Tecnick\File\Exception If reading image content fails.
     */
    protected function getRawData(string $image): array
    {
        // default data to return
        $data = [
            'bits' => 8, // number of bits per channel
            'channels' => 3, // number of channels
            'colspace' => 'DeviceRGB', // color space
            'data' => '', // PDF image data
            'exturl' => false, // true if the image is an exernal URL that should not be embedded
            'file' => '', // source file name or URL
            'filter' => 'FlateDecode', // decoding filter
            'height' => 0, // image height in pixels
            'icc' => '', // ICC profile
            'ismask' => false, // true if the image is a transparency mask
            'key' => '', // image key
            'mapto' => IMAGETYPE_PNG, // type to convert to
            'native' => false, // true if the image is PNG or JPEG
            'obj' => 0, // PDF object number
            'obj_alt' => 0,
            'obj_icc' => 0,
            'obj_pal' => 0,
            'pal' => '', // colour palette
            'parms' => '', // additional PDF decoding parameters
            'raw' => '', // raw image data
            'recode' => false,
            'recoded' => false,
            'splitalpha' => false,
            'trns' => [], // colour key masking
            'type' => 0, // image type constant: IMAGETYPE_XXX
            'width' => 0, // image width in pixels
        ];

        if ($image === '' || ($image[0] === '@' || $image[0] === '*') && \strlen($image) === 1) {
            throw new ImageException('Empty image');
        }

        if ($image[0] === '@') { // image from string
            $data['raw'] = \substr($image, 1);
            return $this->getMetaData($data);
        }

        if ($image[0] === '*') { // not-embedded external URL
            $data['exturl'] = true;
            $image = \substr($image, 1);
        }

        $data['file'] = $image;
        $raw = $this->file->getFileData($image);
        if ($raw === false) {
            throw new ImageException('Unable to read image file: ' . $image);
        }

        $data['raw'] = $raw;

        return $this->getMetaData($data);
    }

    /**
     * Get the image meta data.
     *
     * @param ImageBaseData $data Image raw data.
     *
     * @return ImageRawData Image raw data array.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image format is invalid.
     */
    protected function getMetaData(array $data): array
    {
        try {
            \set_error_handler(static fn(): bool => true);
            $meta = \getimagesizefromstring($data['raw']);
        } catch (\Exception $exception) {
            throw new ImageException('Invalid image format: ' . (string) $exception);
        } finally {
            \restore_error_handler();
        }

        if ($meta === false) {
            throw new ImageException('Invalid image format');
        }

        $data['width'] = $meta[0];
        $data['height'] = $meta[1];
        $data['type'] = $meta[2];
        $data['native'] = isset(self::NATIVE[$data['type']]);
        $data['mapto'] = \in_array($data['type'], self::LOSSLESS, true) ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        /** @var array{'bits'?: int, 'channels'?: int} $meta */
        if (isset($meta['bits'])) {
            $data['bits'] = $meta['bits'];
        }

        if (isset($meta['channels']) && $meta['channels'] !== 0) {
            $data['channels'] = (int) $meta['channels'];
        }

        if (isset(self::COLSPACEMAP[$data['channels']])) {
            $data['colspace'] = self::COLSPACEMAP[$data['channels']];
        }

        return $data;
    }

    /**
     * Get the resized image raw data
     * (always convert the image type to a native format: PNG or JPEG).
     *
     * @param ImageBaseData $data   Image raw data as returned by getImageRawData.
     * @param int          $width   New width in pixels.
     * @param int          $height  New height in pixels.
     * @param bool         $alpha   If true save the alpha channel information,
     *                              if false merge the alpha channel (PNG mode).
     * @param int          $quality Quality for JPEG files
     *                              (0 = max compression; 100 = best quality, bigger file).
     *
     * @return ImageRawData Image raw data array.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the image cannot be resized.
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    protected function getResizedRawData(
        array $data,
        int $width,
        int $height,
        bool $alpha = true,
        int $quality = 100,
    ): array {
        if ($width <= 0 || $height <= 0) {
            throw new ImageException('Image width and/or height are empty');
        }

        $img = \imagecreatefromstring($data['raw']);
        if ($img === false) {
            throw new ImageException('Unable to create new image from string');
        }

        $newimg = \imagecreatetruecolor($width, $height);
        if ($newimg === false) {
            throw new ImageException('Unable to create new resized image');
        }

        \imageinterlace($newimg, false);

        if ($alpha) {
            $trid = \imagecolorallocate($newimg, 0, 0, 0);
            if ($trid === false) {
                throw new ImageException('Unable to allocate alpha color for transparency');
            }
            \imagecolortransparent($newimg, $trid);
        } else {
            $tid = \imagecolortransparent($img);
            $palsize = \imagecolorstotal($img);
            if ($tid >= 0 && $palsize > 0 && $tid < $palsize) {
                // set transparency for Indexed image
                /** @var array{'red': int, 'green': int, 'blue': int, 'alpha': int} $tcol */
                $tcol = \imagecolorsforindex($img, $tid);
                $trid = \imagecolorallocate($newimg, $tcol['red'], $tcol['green'], $tcol['blue']);
                if ($trid === false) {
                    throw new ImageException('Unable to allocate color for transparency');
                }
                \imagefill($newimg, 0, 0, $trid);
                \imagecolortransparent($newimg, $trid);
            }
        }

        \imagealphablending($newimg, !$alpha);
        \imagesavealpha($newimg, $alpha);

        \imagecopyresampled($newimg, $img, 0, 0, 0, 0, $width, $height, $data['width'], $data['height']);

        \ob_start();
        if ($data['mapto'] === IMAGETYPE_PNG) {
            \imagepng($newimg, null, 9, PNG_ALL_FILTERS);
        } else {
            \imagejpeg($newimg, null, $quality);
        }
        $ogc = \ob_get_clean();
        if ($ogc === false) {
            throw new ImageException('Unable to extract alpha channel');
        }

        $data['raw'] = $ogc;
        $data['exturl'] = false;
        $data['recoded'] = true;
        return $this->getMetaData($data);
    }

    /**
     * Extract the alpha channel as separate image to be used as a mask.
     *
     * @param ImageBaseData $data Image raw data as returned by getImageRawData.
     *
     * @return ImageRawData Image raw data array.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception If the alpha channel cannot be extracted.
     */
    protected function getAlphaChannelRawData(array $data): array
    {
        $img = \imagecreatefromstring($data['raw']);
        if ($img === false) {
            throw new ImageException('Unable to create alpha channel image from string');
        }

        $newimg = \imagecreate(\max(1, $data['width']), \max(1, $data['height']));
        if ($newimg === false) {
            throw new ImageException('Unable to create new empty alpha channel image');
        }

        \imageinterlace($newimg, false);

        // generate gray scale palette (0 -> 255)
        for ($col = 0; $col < 256; ++$col) {
            \imagecolorallocate($newimg, $col, $col, $col);
        }

        // extract alpha channel
        for ($xpx = 0; $xpx < $data['width']; ++$xpx) {
            for ($ypx = 0; $ypx < $data['height']; ++$ypx) {
                $colindex = \imagecolorat($img, $xpx, $ypx);
                if ($colindex === false) {
                    throw new ImageException('Unable to extract alpha channel color index');
                }

                // get and correct gamma color
                /** @var array{'red': int, 'green': int, 'blue': int, 'alpha': int} $color */
                $color = \imagecolorsforindex($img, $colindex);
                // GD alpha is only 7 bit (0 -> 127); 2.2 is the gamma value
                $alpha = (int) ((((float) (127 - $color['alpha']) / 127) ** 2.2) * 255);
                \imagesetpixel($newimg, $xpx, $ypx, $alpha);
            }
        }

        \ob_start();
        \imagepng($newimg, null, 9, PNG_ALL_FILTERS);
        $ogc = \ob_get_clean();
        if ($ogc === false) {
            throw new ImageException('Unable to extract alpha channel');
        }

        $data['raw'] = $ogc;
        $data['channels'] = 1;
        $data['colspace'] = 'DeviceGray';
        $data['exturl'] = false;
        $data['recoded'] = true;
        $data['ismask'] = true;
        return $this->getMetaData($data);
    }
}
