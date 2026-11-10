<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Http\Connector;

abstract class Resource
{
    public function __construct(protected Connector $connector) {}
}
