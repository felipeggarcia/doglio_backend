<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFavorite;

class UserFavoritePolicy
{
    public function delete(User $user, UserFavorite $favorite): bool
    {
        return $user->id === $favorite->user_id;
    }

    public function update(User $user, UserFavorite $favorite): bool
    {
        return $user->id === $favorite->user_id;
    }
}
