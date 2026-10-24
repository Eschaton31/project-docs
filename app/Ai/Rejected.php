<?php

namespace App\Ai;

use RuntimeException;

/**
 * A model suggestion that failed validation; the message is the reason it was dropped.
 */
final class Rejected extends RuntimeException {}
