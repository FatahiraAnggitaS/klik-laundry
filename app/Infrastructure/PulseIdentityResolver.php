<?php

namespace App\Infrastructure;

use App\Contracts\IdentityUser;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\ResolvesUsers;

final class PulseIdentityResolver implements ResolvesUsers
{
    /** @var Collection<string, User> */
    private Collection $users;

    public function key(Authenticatable $user): ?string
    {
        return $user instanceof IdentityUser ? $user->publicId() : null;
    }

    /**
     * @param  Collection<int, int|string|null>  $keys
     */
    public function load(Collection $keys): self
    {
        $this->users = User::query()
            ->whereIn('public_id', $keys->filter()->values())
            ->get(['public_id', 'role'])
            ->keyBy('public_id');

        return $this;
    }

    /** @return object{name: string, extra?: string, avatar?: string} */
    public function find(int|string|null $key): object
    {
        $user = $this->users->get((string) $key);

        if ($user === null) {
            return (object) ['name' => 'system'];
        }

        return (object) [
            'name' => $user->public_id,
            'extra' => $user->role->value,
        ];
    }
}
