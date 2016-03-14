<?php
/**
 * Output.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image;

use \Com\Tecnick\Pdf\Image\Import;
use \Com\Tecnick\Pdf\Image\Exception as ImageException;

/**
 * Com\Tecnick\Pdf\Image\Output
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 */
class Output
{
    /**
     * Array of imported images data
     * iid => (iid, key, x, y, width, height, altimgs)
     *
     * @var array
     */
    protected $images;

    /**
     * PDF string block to return containing the image definitions
     *
     * @var string
     */
    protected $out = '';

    /**
     * True if we are in PDF/A mode.
     *
     * @var bool
     */
    protected $pdfa = false;

    /**
     * Unit of measure conversion ratio
     *
     * @var float
     */
    protected $kunit = 1.0;

    /**
     * Current PDF object number
     *
     * @var int
     */
    protected $pon;

    /**
     * Import object that contains the imported images data.
     *
     * @var Import
     */
    protected $imp;

    /**
     * Encrypt object
     *
     * @var Encrypt
     */
    protected $enc;

    /**
     * Initialize images data
     *
     * @param array   $images Array of imported images data
     * @param int     $pon    Current PDF Object Number
     * @param float   $kunit  Unit of measure conversion ratio.
     * @param Import  $imp    Import object that contains the imported images data.
     * @param Encrypt $enc    Encrypt object
     * @param bool    $pdfa   True if we are in PDF/A mode.
     */
    public function __construct(array $images, $pon, $kunit, Import $imp, Encrypt $enc, $pdfa = false)
    {
        $this->images = $images;
        $this->pon = (int) $pon;
        $this->kunit = (float) $kunit;
        $this->imp = $imp;
        $this->enc = $enc;
        $this->pdfa = (bool) $pdfa;

        $this->out = $this->getOutput();
    }

    /**
     * Returns current PDF object number
     *
     * @return int
     */
    public function getObjectNumber()
    {
        return $this->pon;
    }

    /**
     * Returns the PDF images block
     *
     * @return string
     */
    public function getImagesBlock()
    {
        return $this->out;
    }

    /**
     * Get the PDF output string for Images
     *
     * @return string
     */
    protected function getOutput()
    {
        $this->out = '';
        foreach ($this->images as $iid => $img) {
            if (!empty($this->imp[$img['key']]['mask'])) {
                $this->out .= $this->getImage($img, $this->imp[$img['key']]['mask'], 'mask');
            }
            if (!empty($this->imp[$img['key']]['plain'])) {
                $this->out .= $this->getImage($img, $this->imp[$img['key']]['plain'], 'plain');
            } else {
                $this->out .= $this->getImage($img, $this->imp[$img['key']]);
            }
            $this->images[$iid] = $img;
        }
    }

    /**
     * Get the PDF output string for ICC object
     *
     * @param array  $img  Image reference
     * @param array  $data Image raw data
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getIcc(&$img, $data, $sub = '')
    {
        if (empty($data['icc'])) {
            return '';
        }

        $img[$sub.'obj_icc'] = ++$this->pon;
        $stream = $this->enc->encryptString(gzcompress($data['icc']), $this->pon);

        return $this->pon.' 0 obj'."\n"
            .'<</N '.$data['channels']
            .' /Alternate /'.$data['colspace']
            .' /Filter /FlateDecode'
            .' /Length '.strlen($stream)
            .'>> stream'."\n"
            .$stream."\n"
            .'endstream'."\n"
            .'endobj'."\n";
    }

    /**
     * Get the PDF output string for Indexed palette object
     *
     * @param array  $img  Image reference
     * @param array  $data Image raw data
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getPalette(&$img, $data, $sub = '')
    {
        if ($data['colspace'] != 'Indexed') {
            return '';
        }

        $img[$sub.'obj_pal'] = ++$this->pon;
        $stream = $this->enc->encryptString(gzcompress($data['pal']), $this->pon);

        return $this->pon.' 0 obj'."\n"
            .'<</Filter /FlateDecode'
            .' /Length '.strlen($stream)
            .'>> stream'."\n"
            .$stream."\n"
            .'endstream'."\n"
            .'endobj'."\n";
    }

    /**
     * Get the PDF output string for Alternate images object
     *
     * @param array  $img  Image reference
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getAltImages(&$img, $sub = '')
    {
        if ($this->pdfa || empty($img['altimgs'])) {
            return '';
        }

        $img[$sub.'obj_alt'] = ++$this->pon;

        $out = $this->pon.' 0 obj'."\n"
            .'[';
        foreach ($img['altimgs'] as $alt) {
            if (!empty($this->images[$alt][$sub.'obj'])) {
                $out .= ' <<'
                    .' /Image '.$this->images[$alt][$sub.'obj'].' 0 R'
                    .' /DefaultForPrinting '.(empty($this->imp[$alt]['defprint']) ? 'false' : 'true')
                    .' >>';
            }

        }
        $out .= ' ]'."\n"
            .'endobj'."\n";

        return $out;
    }

    /**
     * Get the PDF output string for Image object
     *
     * @param array  $img  Image reference
     * @param array  $data Image raw data
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getImage(&$img, $data, $sub = '')
    {
        $out = $this->getIcc($img, $data, $sub)
                .$this->getPalette($img, $data, $sub)
                .$this->getAltImages($img, $sub);

        $img[$sub.'obj'] = ++$this->pon;

        $out .= $this->pon.' 0 obj'."\n"
            .'<</Type /XObject'
            .' /Subtype /Image'
            .' /Width '.$data['width']
            .' /Height '.$data['height']
            .$this->getColorInfo($img, $data, $sub);

        if (!empty($data['exturl'])) {
            // external stream
            $out .= ' /Length 0'
                .' /F << /FS /URL /F '.$this->enc->escapeDataString($data['exturl'], $this->pon).' >>';
            if (!empty($data['filter'])) {
                $out .= ' /FFilter /'.$data['filter'];
            }
            $out .= ' >> stream'."\n"
                .'endstream'."\n";
        } else {
            if (!empty($data['filter'])) {
                $out .= ' /Filter /'.$data['filter'];
            }
            if (!empty($data['parms'])) {
                $out .= ' '.$data['parms'];
            }

            // Colour Key Masking
            if (!empty($data['trns'])) {
                $trns = $this->getTransparency($data);
                if (!empty($trns)) {
                    $out .= ' /Mask [ '.$trns.']';
                }
            }

            $stream = $this->enc->encryptString($data['data'], $this->pon);
            $out .=' /Length '.strlen($stream)
                .'>> stream'."\n"
                .$stream."\n"
                .'endstream'."\n";
        }

        $out .= 'endobj'."\n";

        return $out;
    }

    /**
     * Get the PDF output string for color and mask information
     *
     * @param array  $img  Image reference
     * @param array  $data Image raw data
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getColorInfo($img, $data, $sub = '')
    {
        $out = '';
        // set color space
        if (!empty($img[$sub.'obj_icc'])) {
            // ICC Colour Space
            $out .= ' /ColorSpace [/ICCBased '.$img[$sub.'obj_icc'].' 0 R]';
        } elseif (!empty($img[$sub.'obj_pal'])) {
            // Indexed Colour Space
            $out .= ' /ColorSpace [/Indexed /DeviceRGB '
                .((strlen($data['pal']) / 3) - 1)
                .' '.$img[$sub.'obj_pal'].' 0 R]';
        } else {
            // Device Colour Space
            $out .= ' /ColorSpace /'.$data['colspace'];
        }
        if ($data['colspace'] == 'DeviceCMYK') {
            $out .= ' /Decode [1 0 1 0 1 0 1 0]';
        }
        $out .= ' /BitsPerComponent '.$data['bits'];
        
        if (!empty($img['maskobj'])) {
            $out .= ' /SMask '.$img['maskobj'].' 0 R';
        }
        
        if (!empty($img[$sub.'obj_alt'])) {
            // reference to alternate images dictionary
            $out .= ' /Alternates '.$img[$sub.'obj_alt'].' 0 R';
        }
        return $out;
    }

    /**
     * Get the PDF output string for color and mask information
     *
     * @param array  $data Image raw data
     *
     * @return string
     */
    protected function getTransparency($data)
    {
        $trns = '';

        if ($data['colspace'] != 'Indexed') {
            // grayscale or RGB
            foreach ($data['trns'] as $idx => $val) {
                if ($val == 0) {
                    $trns .= $idx.' '.$idx.' ';
                }
            }
            return $trns;
        }

        // Indexed
        $maxval = (pow(2, $data['bits']) - 1);
        foreach ($data['trns'] as $idx => $val) {
            if (($val != 0) && ($val != $maxval)) {
                // this is not a binary type mask @TODO: create a SMask
                $trns = '';
                break;
            } elseif (empty($trns) && ($val == 0)) {
                // store the first fully transparent value
                $trns .= $idx.' '.$idx.' ';
            }
        }

        return $trns;
    }
}
