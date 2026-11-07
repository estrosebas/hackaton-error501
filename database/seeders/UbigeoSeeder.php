<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = json_decode(file_get_contents(database_path('data/ubigeo/catalog.json')), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($catalog) {
            foreach (['regions', 'provinces', 'districts'] as $table) {
                foreach (array_chunk($catalog[$table], 300) as $rows) {
                    DB::table($table)->upsert($rows, ['code']);
                }
            }
        });
    }
}
