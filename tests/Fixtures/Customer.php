<?php

namespace Gesflow\Sender\Tests\Fixtures;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\RoutesNotifications;

class Customer
{
    use RoutesNotifications;

    public function __construct(public ?string $phone = '+242 06 612 34 56') {}

    public function routeNotificationForGesflowSender(Notification $notification): ?string
    {
        return $this->phone;
    }
}
