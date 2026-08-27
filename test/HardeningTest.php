<?php

declare(strict_types=1);

/**
 * HardeningTest.php
 *
 * @since     2026-08-27
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

use Com\Tecnick\Pdf\Image\Exception as ImageException;
use Com\Tecnick\Pdf\Image\ImageCacheInterface;
use Com\Tecnick\Pdf\Image\Import;

/**
 * Malformed input and PDF object structure test
 *
 * @since     2026-08-27
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
class HardeningTest extends TestUtil
{
    /**
     * @throws \Com\Tecnick\File\Exception
     */
    protected function getTestObject(?ImageCacheInterface $cache = null): Import
    {
        return new Import(
            kunit: 1.0,
            encrypt: $this->getTestEncrypt(),
            fileHelper: $this->getTestFileHelper(),
            imageCache: $cache,
        );
    }

    protected function getImagePath(string $name): string
    {
        return __DIR__ . '/images/' . $name;
    }

    /**
     * Wrap a payload in a PNG chunk with its length and CRC.
     */
    protected function makeChunk(string $type, string $payload): string
    {
        return \pack('N', \strlen($payload)) . $type . $payload . \pack('N', \crc32($type . $payload));
    }

    /**
     * Split a PNG into its chunks, keeping each chunk's type and full bytes.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function splitChunks(string $png): array
    {
        $chunks = [];
        $offset = 8;
        while ($offset < \strlen($png)) {
            /** @var array{1: int} $unpacked */
            $unpacked = (array) \unpack('N', \substr($png, $offset, 4));
            $len = $unpacked[1];
            $chunks[] = [\substr($png, $offset + 4, 4), \substr($png, $offset, $len + 12)];
            $offset += $len + 12;
        }

        return $chunks;
    }

    /**
     * Insert a chunk into a PNG right before the first chunk of the given type.
     */
    protected function insertChunkBefore(string $png, string $before, string $chunk): string
    {
        $out = \substr($png, 0, 8);
        $done = false;
        foreach ($this->splitChunks($png) as [$type, $raw]) {
            if ($type === $before && !$done) {
                $out .= $chunk;
                $done = true;
            }

            $out .= $raw;
        }

        return $out;
    }

    /**
     * A mask image must not carry an /SMask entry pointing at its own object.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMaskImageHasNoSelfReferencingSmask(): void
    {
        $import = $this->getTestObject();
        $import->add($this->getImagePath('200x100_RGB.png'), null, null, true);

        $out = $import->getOutImagesBlock(1);

        $this->assertStringNotContainsString('/SMask', $out);
        $this->assertStringContainsString('2 0 obj', $out);
    }

    /**
     * An indexed image keeps the /Indexed space and nests the ICC profile as
     * its base, so the palette object stays referenced.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testIndexedImageWithIccProfileKeepsThePalette(): void
    {
        $iccp = '';
        foreach ($this->splitChunks((string) \file_get_contents($this->getImagePath('200x100_RGBICC.png'))) as $chunk) {
            if ($chunk[0] !== 'iCCP') {
                continue;
            }

            $iccp = $chunk[1];
        }

        $this->assertNotSame('', $iccp);

        $png = $this->insertChunkBefore(
            (string) \file_get_contents($this->getImagePath('200x100_INDEX256.png')),
            'PLTE',
            $iccp,
        );

        $import = $this->getTestObject();
        $import->add('@' . $png);
        $out = $import->getOutImagesBlock(1);

        $this->assertStringContainsString('/ColorSpace [/Indexed [/ICCBased 2 0 R] 255 3 0 R]', $out);
        // the ICC object describes the RGB palette entries, not the indices
        $this->assertStringContainsString('/N 3 /Alternate /DeviceRGB', $out);
    }

    /**
     * An alpha-split image keeps its ICC profile on the plain sub-image, which
     * carries the colour, and never on the mask, which carries opacity.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testAlphaSplitImageKeepsTheIccProfile(): void
    {
        $iccp = '';
        foreach ($this->splitChunks((string) \file_get_contents($this->getImagePath('200x100_RGBICC.png'))) as $chunk) {
            if ($chunk[0] !== 'iCCP') {
                continue;
            }

            $iccp = $chunk[1];
        }

        $png = $this->insertChunkBefore(
            (string) \file_get_contents($this->getImagePath('200x100_RGBALPHA.png')),
            'IDAT',
            $iccp,
        );

        $import = $this->getTestObject();
        $import->add('@' . $png);
        $data = $import->getImageDataByKey($import->getKey('@' . $png));

        $this->assertTrue($data['splitalpha']);
        $this->assertNotSame('', $data['icc']);
        $this->assertNotSame('', $data['plain']['icc'] ?? '');
        $this->assertSame('', $data['mask']['icc'] ?? 'missing');
    }

    /**
     * The object number is readable before the first output pass.
     *
     * @throws \Com\Tecnick\File\Exception
     */
    public function testGetObjectNumberBeforeOutput(): void
    {
        $this->assertSame(0, $this->getTestObject()->getObjectNumber());
    }

    /**
     * Dimensions beyond the pixel budget are rejected before reaching GD.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testOversizedImageIsRejected(): void
    {
        $import = $this->getTestObject();

        try {
            $import->add($this->getImagePath('200x100_RGB.png'), 40000, 40000);
            $this->fail('The oversized image was not rejected');
        } catch (ImageException $exception) {
            $this->assertStringContainsString('Image is too large', $exception->getMessage());
        }
    }

    /**
     * A cache entry that does not have the expected shape is ignored and the
     * image is imported.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMalformedExternalCacheEntryIsIgnored(): void
    {
        $cache = new class implements ImageCacheInterface {
            /**
             * @return ImageRawData|null
             */
            public function get(string $key): ?array
            {
                /** @var ImageRawData */
                return ['width' => 1, 'height' => 1];
            }

            /**
             * @param ImageRawData $data
             */
            public function set(string $key, array $data): void {}
        };

        $import = $this->getTestObject($cache);
        $src = $this->getImagePath('200x100_RGB.png');
        $import->add($src);

        $data = $import->getImageDataByKey($import->getKey($src));

        $this->assertSame(200, $data['width']);
        $this->assertSame('DeviceRGB', $data['colspace']);
        $this->assertNotSame('', $data['data']);
    }

    /**
     * A cache backend failure never breaks the import.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testThrowingExternalCacheDoesNotBreakImport(): void
    {
        $cache = new class implements ImageCacheInterface {
            /**
             * @return ImageRawData|null
             *
             * @throws \RuntimeException Always, to simulate a backend failure.
             */
            public function get(string $key): ?array
            {
                throw new \RuntimeException('backend down');
            }

            /**
             * @param ImageRawData $data
             *
             * @throws \RuntimeException Always, to simulate a backend failure.
             */
            public function set(string $key, array $data): void
            {
                throw new \RuntimeException('backend down');
            }
        };

        $import = $this->getTestObject($cache);
        $src = $this->getImagePath('200x100_RGB.png');
        $import->add($src);

        $this->assertSame(200, $import->getImageDataByKey($import->getKey($src))['width']);
    }

    /**
     * A tRNS chunk too short for the colour space is discarded.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testTruncatedTrnsChunkIsIgnored(): void
    {
        $png = $this->insertChunkBefore(
            (string) \file_get_contents($this->getImagePath('200x100_GRAY.png')),
            'IDAT',
            $this->makeChunk('tRNS', ''),
        );

        $import = $this->getTestObject();
        $import->add('@' . $png);
        $out = $import->getOutImagesBlock(1);

        $this->assertStringNotContainsString('/Mask', $out);
    }

    /**
     * A second tRNS chunk replaces the first instead of appending to it, so
     * the colour key always holds exactly one range per colour component.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testDuplicateTrnsChunkReplacesTheFirst(): void
    {
        $png = (string) \file_get_contents($this->getImagePath('200x100_RGB.png'));
        $png = $this->insertChunkBefore($png, 'IDAT', $this->makeChunk('tRNS', "\x00\x01\x00\x02\x00\x03"));
        $png = $this->insertChunkBefore($png, 'IDAT', $this->makeChunk('tRNS', "\x00\x04\x00\x05\x00\x06"));

        $import = $this->getTestObject();
        $import->add('@' . $png);
        $out = $import->getOutImagesBlock(1);

        // the last chunk wins, and only its three ranges are emitted
        $this->assertStringContainsString('/Mask [ 4 4 5 5 6 6 ]', $out);
    }

    /**
     * A palette whose length is not a multiple of 3 is rejected.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMalformedPaletteIsRejected(): void
    {
        $png = (string) \file_get_contents($this->getImagePath('200x100_INDEX16.png'));
        $out = \substr($png, 0, 8);
        foreach ($this->splitChunks($png) as [$type, $raw]) {
            $out .= $type === 'PLTE' ? $this->makeChunk('PLTE', 'ABCD') : $raw;
        }

        $import = $this->getTestObject();

        try {
            $import->add('@' . $out);
            $this->fail('The malformed palette was not rejected');
        } catch (ImageException $exception) {
            $this->assertStringContainsString('malformed color palette', $exception->getMessage());
        }
    }

    /**
     * A PNG ICC profile that does not describe the image colour space is
     * dropped rather than embedded with a mismatched /N.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMismatchedIccProfileIsDropped(): void
    {
        // an RGB profile grafted into a greyscale image
        $profile = \str_pad('', 16, "\x00") . 'RGB ' . \str_pad('', 16, "\x00") . 'acsp' . \str_pad('', 96, "\x00");
        $payload = "p\x00\x00" . (string) \gzcompress($profile);

        $png = $this->insertChunkBefore(
            (string) \file_get_contents($this->getImagePath('200x100_GRAY.png')),
            'IDAT',
            $this->makeChunk('iCCP', $payload),
        );

        $import = $this->getTestObject();
        $import->add('@' . $png);

        $this->assertSame('', $import->getImageDataByKey($import->getKey('@' . $png))['icc']);
        $this->assertStringNotContainsString('/ICCBased', $import->getOutImagesBlock(1));
    }

    /**
     * A bit depth that is illegal for the colour type is rejected.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testInvalidBitDepthIsRejected(): void
    {
        $png = (string) \file_get_contents($this->getImagePath('200x100_RGB.png'));
        // colour type 2 only allows depths 8 and 16
        $png[24] = "\x04";

        $import = $this->getTestObject();

        try {
            $import->add('@' . $png);
            $this->fail('The invalid bit depth was not rejected');
        } catch (ImageException $exception) {
            $this->assertStringContainsString('unsupported bit depth', $exception->getMessage());
        }
    }

    /**
     * Bytes resembling an ICC_PROFILE header outside the marker chain do not
     * replace the profile carried by the APP2 segments.
     */
    public function testJpegIgnoresIccProfileOutsideTheMarkerChain(): void
    {
        $raw = (string) \file_get_contents($this->getImagePath('200x100_RGBICC.jpg'));
        $fake = \pack('n', 116) . "ICC_PROFILE\x00\x01\x01" . \str_repeat('A', 36) . 'acsp' . \str_repeat('B', 60);

        $import = new \Com\Tecnick\Pdf\Image\Import\Jpeg();
        $genuine = $import->getData($this->getJpegData($raw))['icc'];
        $hijacked = $import->getData($this->getJpegData($raw . $fake))['icc'];

        $this->assertNotSame('', $genuine);
        $this->assertSame($genuine, $hijacked);
    }

    /**
     * A profile whose announced segments are not all present is discarded.
     */
    public function testJpegRejectsIncompleteIccSegmentChain(): void
    {
        $body = \str_pad('', 36, "\x00") . 'acsp' . \str_pad('', 160, "\x00");
        $part = \substr($body, 0, 100);

        // segment 1 of an announced 2, with the second one missing
        $payload = "ICC_PROFILE\x00\x01\x02" . $part;
        $raw = "\xff\xd8\xff\xe2" . \pack('n', \strlen($payload) + 2) . $payload . "\xff\xd9";

        $import = new \Com\Tecnick\Pdf\Image\Import\Jpeg();

        $this->assertSame('', $import->getData($this->getJpegData($raw))['icc']);
    }

    /**
     * An image with an alpha channel imported as a mask carries the samples of
     * its flattened variant.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMaskImportOfAlphaImageHasImageData(): void
    {
        $import = $this->getTestObject();
        $src = $this->getImagePath('200x100_RGBALPHA.png');
        $import->add($src, null, null, true);

        $data = $import->getImageDataByKey($import->getKey($src, 0, 0, 100, true));

        $this->assertNotSame('', $data['mask']['data'] ?? '');
        $this->assertStringNotContainsString('/Length 0>>', $import->getOutImagesBlock(1));
    }

    /**
     * The same source imported as a mask and as a normal image yields two
     * distinct entries, because the two variants hold different data.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMaskAndPlainImportsOfTheSameImageDoNotShareTheCache(): void
    {
        $import = $this->getTestObject();
        $src = $this->getImagePath('200x100_RGB.png');
        $import->add($src);
        $import->add($src, null, null, true);

        $import->getOutImagesBlock(1);

        $this->assertSame(' /IMG1 2 0 R /IMGmask2 3 0 R', $import->getXobjectDict());
    }

    /**
     * The plain half of an alpha split keeps the source colour unchanged, as
     * the opacity is carried by the /SMask.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testPlainImageKeepsUnpremultipliedColors(): void
    {
        // 4 white pixels at decreasing opacity (GD alpha 0 -> 127)
        $img = \imagecreatetruecolor(4, 1);
        \imagesavealpha($img, true);
        \imagealphablending($img, false);
        foreach ([0, 32, 64, 127] as $xpx => $alpha) {
            \imagesetpixel($img, $xpx, 0, (int) \imagecolorallocatealpha($img, 255, 255, 255, $alpha));
        }

        \ob_start();
        \imagepng($img, null, 9, PNG_ALL_FILTERS);
        $png = (string) \ob_get_clean();

        $import = $this->getTestObject();
        $import->add('@' . $png);
        $data = $import->getImageDataByKey($import->getKey('@' . $png));

        $plain = \imagecreatefromstring($data['plain']['raw'] ?? '');
        $this->assertNotFalse($plain);

        // the two partly transparent pixels keep the source white
        $this->assertSame(0xFFFFFF, \imagecolorat($plain, 1, 0) & 0xFFFFFF);
        $this->assertSame(0xFFFFFF, \imagecolorat($plain, 2, 0) & 0xFFFFFF);
    }

    /**
     * An image that took the re-encode path does not carry the flag over to
     * the sub-images it is split into.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testInterlacedImageKeepsTheChannelCount(): void
    {
        $import = $this->getTestObject();
        $src = $this->getImagePath('200x100_RGBINT.png');
        $import->add($src);

        $data = $import->getImageDataByKey($import->getKey($src));

        $this->assertFalse($data['recode']);
        $this->assertSame(3, $data['plain']['channels'] ?? 0);
        $this->assertStringContainsString('/Colors 3', $data['plain']['parms'] ?? '');
    }

    /**
     * A JPEG ICC profile that does not describe the image colour space is
     * dropped rather than embedded with a mismatched /N.
     */
    public function testJpegMismatchedIccProfileIsDropped(): void
    {
        // a CMYK profile carried by an RGB image
        $body = \str_pad('', 16, "\x00") . 'CMYK' . \str_pad('', 16, "\x00") . 'acsp' . \str_pad('', 96, "\x00");
        $payload = "ICC_PROFILE\x00\x01\x01" . $body;
        $raw = "\xff\xd8\xff\xe2" . \pack('n', \strlen($payload) + 2) . $payload . "\xff\xd9";

        $import = new \Com\Tecnick\Pdf\Image\Import\Jpeg();

        $this->assertSame('', $import->getData($this->getJpegData($raw))['icc']);
    }

    /**
     * PDF object numbers and the output flag of an entry loaded from the
     * external cache are reset.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testExternalCacheEntryWithStaleObjectNumbersIsReset(): void
    {
        $src = $this->getImagePath('200x100_RGB.png');

        $cache = new SpyImageCache();
        $this->getTestObject($cache)->add($src);
        $key = $cache->setKeys[0] ?? '';

        $stale = $cache->fetch($key);
        $stale['obj'] = 99;
        $stale['out'] = true;
        $cache->store[$key] = $stale;

        $import = $this->getTestObject($cache);
        $import->add($src);
        $out = $import->getOutImagesBlock(1);

        $this->assertStringContainsString('2 0 obj', $out);
        $this->assertSame(' /IMG1 2 0 R', $import->getXobjectDict());
    }

    /**
     * An iCCP chunk declaring a length shorter than its own profile name is
     * rejected.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testMalformedIccpChunkLengthIsRejected(): void
    {
        // the declared length covers the name but neither the compression
        // method byte nor any profile data
        $chunk = \pack('N', 5) . 'iCCP' . "name\x00\x00" . \pack('N', 0);

        $png = $this->insertChunkBefore(
            (string) \file_get_contents($this->getImagePath('200x100_RGB.png')),
            'IDAT',
            $chunk,
        );

        $import = $this->getTestObject();

        try {
            $import->add('@' . $png);
            $this->fail('The malformed iCCP chunk was not rejected');
        } catch (ImageException $exception) {
            $this->assertStringContainsString('malformed iCCP chunk', $exception->getMessage());
        }
    }

    /**
     * A format that can carry an alpha channel is re-encoded to PNG, so the
     * transparency survives as a soft mask.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testWebpAlphaChannelIsPreserved(): void
    {
        if (!\function_exists('imagewebp')) {
            $this->markTestSkipped('GD is built without WebP support');
        }

        // 4 red pixels at decreasing opacity (GD alpha 0 -> 126)
        $img = \imagecreatetruecolor(4, 1);
        \imagesavealpha($img, true);
        \imagealphablending($img, false);
        foreach ([0, 42, 84, 126] as $xpx => $alpha) {
            \imagesetpixel($img, $xpx, 0, (int) \imagecolorallocatealpha($img, 255, 0, 0, $alpha));
        }

        \ob_start();
        \imagewebp($img, null, 100);
        $webp = (string) \ob_get_clean();

        $import = $this->getTestObject();
        $import->add('@' . $webp);

        $data = $import->getImageDataByKey($import->getKey('@' . $webp));

        $this->assertTrue($data['splitalpha']);
        $this->assertSame('DeviceGray', $data['mask']['colspace'] ?? '');
        $this->assertStringContainsString('/SMask', $import->getOutImagesBlock(1));
    }

    /**
     * A linked PNG is embedded: a chunk container cannot be decoded by the
     * single /FFilter an external stream is read through.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testExternalUrlPngIsEmbeddedInsteadOfLinked(): void
    {
        $import = $this->getTestObject();
        $import->add('*' . $this->getImagePath('200x100_RGB.png'));

        $out = $import->getOutImagesBlock(1);

        $this->assertStringNotContainsString('/FFilter', $out);
        $this->assertStringNotContainsString('/FS /URL', $out);
        $this->assertStringContainsString('/Filter /FlateDecode', $out);
        $this->assertStringNotContainsString('/Length 0>>', $out);
    }

    /**
     * A linked JPEG stays external: the file is itself a DCTDecode stream.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testExternalUrlJpegStaysLinked(): void
    {
        $import = $this->getTestObject();
        $import->add('*' . $this->getImagePath('200x100_RGB.jpg'));

        $out = $import->getOutImagesBlock(1);

        $this->assertStringContainsString('/FS /URL', $out);
        $this->assertStringContainsString('/FFilter /DCTDecode', $out);
    }

    /**
     * An indexed palette holding partially opaque entries is split into a
     * plain image plus a soft mask.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testIndexedPartialTransparencyBecomesASoftMask(): void
    {
        $png = $this->getIndexedPng("\xff\x00\x80\x40");

        $import = $this->getTestObject();
        $import->add('@' . $png);

        $data = $import->getImageDataByKey($import->getKey('@' . $png));

        $this->assertTrue($data['splitalpha']);
        $this->assertSame('DeviceGray', $data['mask']['colspace'] ?? '');

        $out = $import->getOutImagesBlock(1);
        $this->assertStringContainsString('/SMask', $out);
        $this->assertStringNotContainsString('/Indexed', $out);
    }

    /**
     * A palette holding only opaque and fully transparent entries keeps the
     * indexed space and its colour key mask.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testIndexedBinaryTransparencyKeepsTheColorKeyMask(): void
    {
        $png = $this->getIndexedPng("\xff\x00\xff\x00");

        $import = $this->getTestObject();
        $import->add('@' . $png);

        $out = $import->getOutImagesBlock(1);

        $this->assertStringContainsString('/Indexed', $out);
        $this->assertStringContainsString('/Mask [ 1 1 3 3 ]', $out);
        $this->assertStringNotContainsString('/SMask', $out);
    }

    /**
     * The palette, transparency and decode parameters of a discarded parse do
     * not reach the sub-images of the re-encoded image.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testReparsedImageDoesNotInheritTheDiscardedTransparency(): void
    {
        $png = $this->getIndexedPng("\xff\x00\x80\x40");

        $import = $this->getTestObject();
        $import->add('@' . $png);

        $data = $import->getImageDataByKey($import->getKey('@' . $png));

        $this->assertSame([], $data['plain']['trns'] ?? [null]);
        $this->assertSame([], $data['mask']['trns'] ?? [null]);
        $this->assertSame('', $data['plain']['pal'] ?? 'unset');
        $this->assertStringContainsString('/Colors 3', $data['plain']['parms'] ?? '');
        $this->assertStringNotContainsString('/Mask', $import->getOutImagesBlock(1));
    }

    /**
     * A PNG whose chunks carry no samples is rejected.
     *
     * @throws \Com\Tecnick\File\Exception
     */
    public function testPngWithoutImageDataIsRejected(): void
    {
        $png =
            "\x89PNG\r\n\x1a\n"
            . $this->makeChunk('IHDR', \pack('NN', 4, 2) . \chr(8) . \chr(2) . "\x00\x00\x00")
            . $this->makeChunk('IEND', '');

        $import = $this->getTestObject();

        try {
            $import->add('@' . $png);
            $this->fail('The PNG without image data was not rejected');
        } catch (ImageException $exception) {
            $this->assertStringContainsString('missing image data', $exception->getMessage());
        }
    }

    /**
     * Two images with the same content but different alternates are emitted as
     * two objects.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testAlternatesAreNotSharedByEqualImages(): void
    {
        $import = $this->getTestObject();
        $alt = $import->add($this->getImagePath('200x100_GRAY.jpg'));
        $import->add($this->getImagePath('200x100_RGB.jpg'));
        $import->add($this->getImagePath('200x100_RGB.jpg'), null, null, false, 100, false, [$alt]);

        $out = $import->getOutImagesBlock(1);

        // the plain instance and the one carrying the alternates are distinct
        $this->assertSame(' /IMG1 2 0 R /IMG2 3 0 R /IMG3 5 0 R', $import->getXobjectDict());
        $this->assertStringContainsString('4 0 obj' . "\n" . '[ << /Image 2 0 R', $out);
        $this->assertSame(1, \substr_count($out, '/Alternates'));
        $this->assertStringContainsString('/Alternates 4 0 R', $out);
    }

    /**
     * Two images with the same content and the same alternates still share a
     * single object.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     */
    public function testEqualImagesWithEqualAlternatesShareOneObject(): void
    {
        $import = $this->getTestObject();
        $alt = $import->add($this->getImagePath('200x100_GRAY.jpg'));
        $import->add($this->getImagePath('200x100_RGB.jpg'), null, null, false, 100, false, [$alt]);
        $import->add($this->getImagePath('200x100_RGB.jpg'), null, null, false, 100, false, [$alt]);

        $out = $import->getOutImagesBlock(1);

        $this->assertSame(' /IMG1 2 0 R /IMG2 4 0 R /IMG3 4 0 R', $import->getXobjectDict());
        $this->assertSame(1, \substr_count($out, '/Alternates'));
    }

    /**
     * Build a 4x1 indexed PNG with a four-colour palette and the given tRNS
     * alpha bytes.
     */
    protected function getIndexedPng(string $trns): string
    {
        return (
            "\x89PNG\r\n\x1a\n"
            . $this->makeChunk('IHDR', \pack('NN', 4, 1) . \chr(8) . \chr(3) . "\x00\x00\x00")
            . $this->makeChunk('PLTE', "\xff\x00\x00\x00\xff\x00\x00\x00\xff\xff\xff\xff")
            . $this->makeChunk('tRNS', $trns)
            . $this->makeChunk('IDAT', (string) \gzcompress("\x00\x00\x01\x02\x03", 9))
            . $this->makeChunk('IEND', '')
        );
    }

    /**
     * Build the base data array for a raw JPEG stream.
     *
     * @return ImageBaseData
     */
    protected function getJpegData(string $raw): array
    {
        return [
            'bits' => 8,
            'channels' => 3,
            'colspace' => 'DeviceRGB',
            'data' => '',
            'exturl' => false,
            'file' => '',
            'filter' => '',
            'height' => 100,
            'icc' => '',
            'ismask' => false,
            'key' => 'test',
            'mapto' => IMAGETYPE_JPEG,
            'native' => true,
            'obj' => 0,
            'obj_alt' => 0,
            'obj_icc' => 0,
            'obj_pal' => 0,
            'pal' => '',
            'parms' => '',
            'raw' => $raw,
            'recode' => false,
            'recoded' => false,
            'splitalpha' => false,
            'trns' => [],
            'type' => IMAGETYPE_JPEG,
            'width' => 200,
        ];
    }
}
