<?php

namespace Gesflow\Sender\Exceptions;

/**
 * 5xx : erreur de la plateforme, après épuisement des nouvelles tentatives.
 */
class ServerException extends ApiException {}
