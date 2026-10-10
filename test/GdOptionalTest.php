<?php

/**
 * GdOptionalTest.php
 *
 * @since     2026-10-09
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

use Com\Tecnick\Pdf\Image\Import;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Image import without the gd extension
 *
 * @since     2026-10-09
 * @category  Library
 * @package   PdfImage
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-image
 */
class GdOptionalTest extends TestUtil
{
    /**
     * Returns an importer that behaves as if the gd extension were not loaded.
     *
     * @throws \Com\Tecnick\File\Exception
     */
    protected function getImportWithoutGd(): Import
    {
        return new class(0.75, $this->getTestEncrypt(), $this->getTestFileHelper()) extends Import {
            #[\Override]
            protected function isGdLoaded(): bool
            {
                return false;
            }
        };
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nativeImageProvider(): array
    {
        return [
            'jpeg' => ['200x100_RGB.jpg'],
            'cmyk jpeg' => ['200x100_CMYK.jpg'],
            'gray png' => ['200x100_GRAY.png'],
            'rgb png' => ['200x100_RGB.png'],
            'palette png' => ['200x100_INDEX256.png'],
        ];
    }

    /**
     * JPEG and PNG images without an alpha channel are imported at their own size without gd.
     *
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    #[DataProvider('nativeImageProvider')]
    public function testNativeImagesNeedNoGd(string $file): void
    {
        $import = $this->getImportWithoutGd();
        $iid = $import->add(__DIR__ . '/images/' . $file);

        $this->assertSame(200, $import->getImageDataByKey($import->getKey(__DIR__ . '/images/' . $file))['width']);
        $this->assertGreaterThan(0, $iid);
    }

    /**
     * @return array<string, array{string, ?int, ?int, string}>
     */
    public static function conversionProvider(): array
    {
        return [
            'alpha channel' => ['200x100_RGBALPHA.png', null, null, 'split its alpha channel'],
            'resize' => ['200x100_RGB.png', 100, 50, 'resize or re-encode it'],
        ];
    }

    /**
     * An import that needs a conversion throws an exception naming the gd extension.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     */
    #[DataProvider('conversionProvider')]
    public function testConversionsNeedGd(string $file, ?int $width, ?int $height, string $action): void
    {
        $import = $this->getImportWithoutGd();

        try {
            $import->add(__DIR__ . '/images/' . $file, $width, $height);
        } catch (\Com\Tecnick\Pdf\Image\Exception $exc) {
            $this->assertSame(
                'The gd extension is required to ' . $action . ': unable to import the image',
                $exc->getMessage(),
            );
            return;
        }

        $this->fail('Expected an exception naming the gd extension');
    }
}
