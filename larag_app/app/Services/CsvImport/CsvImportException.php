<?php

namespace App\Services\CsvImport;

use RuntimeException;

/** A CSV could not be used for import; the message is safe to show the user. */
class CsvImportException extends RuntimeException {}
