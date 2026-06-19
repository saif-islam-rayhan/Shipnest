<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(User::class, 'model', config('permission.table_names.model_has_roles'), 'role_id');
    }
}
