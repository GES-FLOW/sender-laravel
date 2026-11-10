<?php

namespace Gesflow\Sender\Exceptions;

/**
 * 401 : clé API absente, invalide, expirée ou révoquée.
 */
class AuthenticationException extends ApiException {}
