# tc-lib-pdf-image

> Image import and embedding utilities for PDF streams.

[![Latest Stable Version](https://poser.pugx.org/tecnickcom/tc-lib-pdf-image/version)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-image)
[![Build](https://github.com/tecnickcom/tc-lib-pdf-image/actions/workflows/check.yml/badge.svg)](https://github.com/tecnickcom/tc-lib-pdf-image/actions/workflows/check.yml)
[![Coverage](https://codecov.io/gh/tecnickcom/tc-lib-pdf-image/graph/badge.svg?token=7RH3BDHTL2)](https://codecov.io/gh/tecnickcom/tc-lib-pdf-image)
[![License](https://poser.pugx.org/tecnickcom/tc-lib-pdf-image/license)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-image)
[![Downloads](https://poser.pugx.org/tecnickcom/tc-lib-pdf-image/downloads)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-image)

[![Donate via PayPal](https://img.shields.io/badge/donate-paypal-87ceeb.svg)](https://www.paypal.com/donate/?hosted_button_id=NZUEC5XS8MFBJ)

If this library helps your image workflow, please consider [supporting development via PayPal](https://www.paypal.com/donate/?hosted_button_id=NZUEC5XS8MFBJ).

---

## Overview

`tc-lib-pdf-image` handles image import, conversion, and output structures used by PDF generators.

| | |
|---|---|
| **Namespace** | `\Com\Tecnick\Pdf\Image` |
| **Author** | Nicola Asuni <info@tecnick.com> |
| **License** | [GNU LGPL v3](https://www.gnu.org/copyleft/lesser.html) - see [LICENSE](LICENSE) |
| **API docs** | <https://tcpdf.org/docs/srcdoc/tc-lib-pdf-image> |
| **Packagist** | <https://packagist.org/packages/tecnickcom/tc-lib-pdf-image> |

---

## Features

### Import Support
- Native handling for PNG and JPEG
- Additional format handling through GD processing paths
- Transparency and palette-related metadata handling

### PDF Integration
- Image caching keys for repeated assets
- Alternate image support for print/display contexts
- Output helpers for embedding image objects

---

## Requirements

- PHP 8.1 or later
- Extensions: `gd`, `zlib`
- Composer

---

## Installation

```bash
composer require tecnickcom/tc-lib-pdf-image
```

---

## Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$img = new \Com\Tecnick\Pdf\Image\Import();
$imageId = $img->add('/path/to/image.png');

var_dump($imageId);
```

---

## Development

```bash
make deps
make help
make qa
```

---

## Packaging

```bash
make rpm
make deb
```

For system packages, bootstrap with:

```php
require_once '/usr/share/php/Com/Tecnick/Pdf/Image/autoload.php';
```

---

## Contributing

Contributions are welcome. Please review [CONTRIBUTING.md](CONTRIBUTING.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), and [SECURITY.md](SECURITY.md).

---

## Contact

Nicola Asuni - <info@tecnick.com>
