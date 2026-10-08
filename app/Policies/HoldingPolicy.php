<?php

namespace App\Policies;

use App\Models\Holding;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HoldingPolicy
{
    /**
     * Holdings, and the transactions and SIPs inside them, are private to their
     * owner. Others get a 404 so holding IDs don't reveal that a holding exists.
     */
    public function manage(User $user, Holding $holding): Response
    {
        return $holding->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
