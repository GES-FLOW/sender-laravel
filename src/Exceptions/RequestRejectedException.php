<?php

namespace Gesflow\Sender\Exceptions;

/**
 * 422 sans détail par champ : la requête est valide mais une règle métier
 * la refuse (solde insuffisant, Sender ID inactif, session WhatsApp non
 * connectée, message ou campagne qui ne peut plus être annulé…).
 */
class RequestRejectedException extends ApiException {}
