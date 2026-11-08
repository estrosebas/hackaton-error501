<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Tags\Tag;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['profile.update', 'media.manage-own', 'posts.create', 'posts.moderate', 'users.manage', 'taxonomy.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('neighbor', 'web')->syncPermissions(['profile.update', 'media.manage-own', 'posts.create']);
        Role::findOrCreate('moderator', 'web')->syncPermissions(['posts.moderate']);
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        foreach (['Servicios', 'Productos', 'Ayuda', 'Intercambios', 'Eventos', 'Emergencias'] as $category) {
            Tag::findOrCreate($category, 'category');
        }
    }
}
