<?php

/**
 * ImportOutputPngEdgeCasesTest.php
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

declare(strict_types=1);

namespace Test;

require_once __DIR__ . '/ImportProtectedMethodsHarness.php';
require_once __DIR__ . '/PngChunkParsingHarness.php';

/**
 * Import, output and PNG chunk parsing edge cases test
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
 * @phpstan-import-type ImageRawData from \Com\Tecnick\Pdf\Image\Import
 */
class ImportOutputPngEdgeCasesTest extends TestUtil
{
    /**
     * @return ImageBaseData
     */
    protected function getBaseData(string $key = 'test'): array
    {
        return [
            'bits' => 8,
            'channels' => 3,
            'colspace' => 'DeviceRGB',
            'data' => 'abc',
            'exturl' => false,
            'file' => '',
            'filter' => 'FlateDecode',
            'height' => 1,
            'icc' => '',
            'ismask' => false,
            'key' => $key,
            'mapto' => IMAGETYPE_PNG,
            'native' => true,
            'obj' => 0,
            'obj_alt' => 0,
            'obj_icc' => 0,
            'obj_pal' => 0,
            'pal' => '',
            'parms' => '',
            'raw' => 'raw',
            'recode' => false,
            'recoded' => false,
            'splitalpha' => false,
            'trns' => [],
            'type' => IMAGETYPE_PNG,
            'width' => 1,
        ];
    }

    /**
     * @return ImageRawData
     */
    protected function getRawData(string $key = 'test'): array
    {
        return $this->getBaseData($key);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    protected function getImportHarness(bool $pdfa = false): ImportProtectedMethodsHarness
    {
        return new ImportProtectedMethodsHarness(
            0.75,
            $this->getTestEncrypt(),
            $this->getTestFileHelper(),
            $pdfa,
            false,
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetDataThrowsWhenImageIsNotNative(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $import = $this->getImportHarness();
        $data = $this->getRawData();
        $data['native'] = false;
        $import->callGetData($data, 10, 10, 90);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetDataThrowsWhenNativeTypeIsUnknown(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $import = $this->getImportHarness();
        $data = $this->getRawData();
        $data['type'] = IMAGETYPE_GIF;
        $import->callGetData($data, 10, 10, 90);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetResizedRawDataRejectsInvalidRawImage(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $import = $this->getImportHarness();
        $data = $this->getBaseData();
        $data['raw'] = 'not-image-binary';

        \set_error_handler(static fn(): bool => true);
        try {
            $import->callGetResizedRawData($data, 10, 10, true, 90);
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetAlphaChannelRawDataRejectsInvalidRawImage(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $import = $this->getImportHarness();
        $data = $this->getBaseData();
        $data['raw'] = 'not-image-binary';

        \set_error_handler(static fn(): bool => true);
        try {
            $import->callGetAlphaChannelRawData($data);
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetOutImageSupportsExternalStreamWithFilter(): void
    {
        $import = $this->getImportHarness();
        $import->setPon(10);

        $img = [
            'iid' => 1,
            'key' => 'ext',
            'width' => 10,
            'height' => 5,
            'defprint' => false,
            'altimgs' => [],
        ];
        $data = $this->getRawData('ext');
        $data['exturl'] = true;
        $data['file'] = 'https://example.test/image.png';
        $data['filter'] = 'ASCIIHexDecode';
        $data['width'] = 10;
        $data['height'] = 5;

        $import->setCacheEntry('ext', $data);
        $out = $import->callGetOutImage($img, $data);

        // the file specification carries the source URL of the external stream
        $this->assertStringContainsString('/Length 0 /F << /FS /URL /F (https://example.test/image.png) >>', $out);
        $this->assertStringContainsString('/FFilter /ASCIIHexDecode', $out);
        // an image XObject is a stream object even when its data is external
        $this->assertStringContainsString('>> stream' . "\n" . 'endstream' . "\n" . 'endobj', $out);
    }

    /**
     * An indexed image without a palette does not emit a negative hival.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testIndexedImageWithEmptyPaletteDoesNotEmitNegativeHival(): void
    {
        $import = $this->getImportHarness();
        $import->setPon(4);

        $img = [
            'iid' => 1,
            'key' => 'idx',
            'width' => 1,
            'height' => 1,
            'defprint' => false,
            'altimgs' => [],
        ];
        $data = $this->getRawData('idx');
        $data['colspace'] = 'Indexed';

        $import->setCacheEntry('idx', $data);
        $out = $import->callGetOutImage($img, $data);

        $this->assertStringContainsString('/ColorSpace [/Indexed /DeviceRGB 0 5 0 R]', $out);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetXobjectDictByKeysFallsBackToPlainAndMaskEntries(): void
    {
        $import = $this->getImportHarness();
        $import->setXobjdict([
            'IMGplain2' => 42,
            'IMGmask3' => 43,
        ]);

        $out = $import->getXobjectDictByKeys([2, 3]);
        $this->assertSame(' /IMGplain2 42 0 R /IMGmask3 43 0 R', $out);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetOutAltImagesSkipsUnknownOrUnbuiltAlternateImages(): void
    {
        $import = $this->getImportHarness();
        $import->setPon(20);

        $import->setImageEntry(2, [
            'iid' => 2,
            'key' => 'alt-no-object',
            'width' => 10,
            'height' => 5,
            'defprint' => true,
            'altimgs' => [],
        ]);

        $altData = $this->getRawData('alt-no-object');
        $altData['obj'] = 0;
        $import->setCacheEntry('alt-no-object', $altData);

        $img = [
            'iid' => 1,
            'key' => 'main',
            'width' => 10,
            'height' => 5,
            'defprint' => false,
            'altimgs' => [999, 2],
        ];
        $data = $this->getRawData('main');

        $out = $import->callGetOutAltImages($img, $data);

        // no alternate resolves to a written object: nothing is emitted and no
        // object number is consumed
        $this->assertSame('', $out);
        $this->assertSame(0, $data['obj_alt']);
        $this->assertSame(20, $import->getObjectNumber());
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetOutAltImagesUsesPlainObjectOfAlphaSplitImage(): void
    {
        $import = $this->getImportHarness();
        $import->setPon(20);

        $import->setImageEntry(2, [
            'iid' => 2,
            'key' => 'alt-split',
            'width' => 10,
            'height' => 5,
            'defprint' => true,
            'altimgs' => [],
        ]);

        // an alpha-split image has no top-level object, only sub-images
        $altData = $this->getRawData('alt-split');
        $altData['obj'] = 0;
        $plain = $this->getBaseData('alt-split');
        $plain['obj'] = 7;
        $altData['plain'] = $plain;
        $import->setCacheEntry('alt-split', $altData);

        $img = [
            'iid' => 1,
            'key' => 'main',
            'width' => 10,
            'height' => 5,
            'defprint' => false,
            'altimgs' => [2],
        ];
        $data = $this->getRawData('main');

        $out = $import->callGetOutAltImages($img, $data);

        $this->assertStringContainsString('21 0 obj', $out);
        $this->assertStringContainsString('<< /Image 7 0 R /DefaultForPrinting true >>', $out);
        $this->assertSame(21, $data['obj_alt']);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetOutTransparencyIndexedSkipsNonZeroValues(): void
    {
        $import = $this->getImportHarness();
        $data = $this->getRawData();
        $data['colspace'] = 'Indexed';
        $data['trns'] = [0 => 0, 1 => 9, 2 => 0];

        // indexed: the fully-transparent palette indices (alpha 0) are masked
        $out = $import->callGetOutTransparency($data);
        $this->assertSame('0 0 2 2 ', $out);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetOutTransparencyRgbUsesColorValues(): void
    {
        $import = $this->getImportHarness();
        $data = $this->getRawData();
        $data['colspace'] = 'DeviceRGB';
        // trns holds the transparent colour samples (R, G, B)
        $data['trns'] = [255, 128, 0];

        $out = $import->callGetOutTransparency($data);
        $this->assertSame('255 255 128 128 0 0 ', $out);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetOutTransparencyGrayUsesColorValue(): void
    {
        $import = $this->getImportHarness();
        $data = $this->getRawData();
        $data['colspace'] = 'DeviceGray';
        $data['trns'] = [128];

        $out = $import->callGetOutTransparency($data);
        $this->assertSame('128 128 ', $out);
    }

    public function testGetTrnsChunkHandlesGrayAndRgbFormats(): void
    {
        $png = new PngChunkParsingHarness();

        $gray = $this->getBaseData();
        $gray['colspace'] = 'DeviceGray';
        $gray['raw'] = "\x00\x7f";
        $gray['trns'] = [];
        $grayOffset = 0;
        $gray = $png->callGetTrnsChunk($gray, $grayOffset, 2);
        $this->assertSame([127], $gray['trns']);
        $this->assertSame(6, $grayOffset);

        $rgb = $this->getBaseData();
        $rgb['colspace'] = 'DeviceRGB';
        $rgb['raw'] = "\x00\x11\x00\x22\x00\x33";
        $rgb['trns'] = [];
        $rgbOffset = 0;
        $rgb = $png->callGetTrnsChunk($rgb, $rgbOffset, 6);
        $this->assertSame([17, 34, 51], $rgb['trns']);
        $this->assertSame(10, $rgbOffset);
    }

    /**
     * A stream too short to hold the PNG header is reported as an invalid image.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \RangeException
     */
    public function testGetDataRejectsTruncatedPngHeader(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $data = $this->getBaseData();
        $data['raw'] = \chr(137) . 'PNG' . \chr(13) . \chr(10) . \chr(26) . \chr(10) . 'IHDR';

        (new \Com\Tecnick\Pdf\Image\Import\Png())->getData($data);
    }

    /**
     * A PNG data stream cut before its IEND chunk is reported as an invalid image.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \RangeException
     */
    public function testGetDataRejectsPngWithoutIendChunk(): void
    {
        $raw = \file_get_contents(__DIR__ . '/images/200x100_RGB.png');
        $this->assertIsString($raw);

        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $data = $this->getBaseData();
        // drop the trailing IEND chunk: length (4) + type (4) + CRC (4)
        $data['raw'] = \substr($raw, 0, -12);

        (new \Com\Tecnick\Pdf\Image\Import\Png())->getData($data);
    }

    /**
     * An iCCP chunk that carries neither a name terminator nor a compression
     * method byte is reported as an invalid image.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \RangeException
     */
    public function testGetIccpChunkRejectsTruncatedChunk(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $png = new PngChunkParsingHarness();
        $data = $this->getBaseData();
        $data['raw'] = 'ICC';
        $offset = 0;

        $png->callGetIccpChunk(new \Com\Tecnick\File\Byte($data['raw']), $data, $offset, 8);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \RangeException
     */
    public function testGetIccpChunkThrowsOnInvalidCompressedProfile(): void
    {
        $this->bcExpectException(\Com\Tecnick\Pdf\Image\Exception::class);

        $png = new PngChunkParsingHarness();
        $data = $this->getBaseData();
        $data['raw'] = "ICC\x00\x00BAD";
        $offset = 0;
        $byte = new \Com\Tecnick\File\Byte($data['raw']);

        \set_error_handler(static fn(): bool => true);
        try {
            $png->callGetIccpChunk($byte, $data, $offset, 8);
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetResizedRawDataPreservesIndexedTransparency(): void
    {
        $import = $this->getImportHarness();

        $raw = \file_get_contents(__DIR__ . '/images/200x100_INDEXALPHA.png');
        $this->assertIsString($raw);

        $data = $this->getBaseData();
        $data['raw'] = $raw;
        $data['width'] = 200;
        $data['height'] = 100;

        // alpha=false exercises the indexed-palette transparency branch
        \set_error_handler(static fn(): bool => true);
        try {
            $resized = $import->callGetResizedRawData($data, 100, 50, false, 90);
        } finally {
            \restore_error_handler();
        }

        $this->assertSame(100, $resized['width']);
        $this->assertSame(50, $resized['height']);
        $this->assertTrue($resized['recoded']);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testGetImageDimensionsByKeyFitWithZeroSourceReturnsBox(): void
    {
        $import = $this->getImportHarness();

        $data = $this->getRawData('zero-source');
        $data['width'] = 0;
        $data['height'] = 0;
        $import->setCacheEntry('zero-source', $data);

        // a zero-sized source falls back to the requested bounding box as-is
        $this->assertSame(
            ['width' => 80, 'height' => 80],
            $import->getImageDimensionsByKey('zero-source', 80, 80, true),
        );
    }
}
