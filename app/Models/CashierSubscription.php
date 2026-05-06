<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierBase;

class CashierSubscription extends CashierBase
{
	protected $table = 'stripe_subscriptions';
}