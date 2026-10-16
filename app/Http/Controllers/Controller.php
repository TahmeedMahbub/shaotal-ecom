<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Logged-in storefront customer id (customers.id) from the session, if any. */
    protected function customerId(): ?int
    {
        $id = session('customer_id');

        return $id ? (int) $id : null;
    }
}
