<?php

namespace JSON\Store\Exceptions;

class NotFound extends \Exception {
  public function __construct (
    string $message = 'Not found',
    int $code = 404,
    \Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
  }
}
