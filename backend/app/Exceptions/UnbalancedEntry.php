<?php

namespace App\Exceptions;

use Exception;

/** Raised when a journal entry's debits do not equal its credits. */
class UnbalancedEntry extends Exception
{
}
