<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DomainTestSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonthNoOverflow();

        foreach (range(1, 2) as $i) {
            DB::table('domains')->insert([
                'name' => "old-domain-$i.com",
                'url' => 'www.old-domain-' . $i . '.com',
                'created_at' => $lastMonth->copy()->addDays($i),
                'updated_at' => $lastMonth->copy()->addDays($i),
            ]);
        }

        foreach (range(1, 4) as $i) {
            DB::table('domains')->insert([
                'name' => "new-domain-$i.com",
                'url' => 'www.new-domain-' . $i . '.com',
                'created_at' => $now->copy()->subDays(4 - $i),
                'updated_at' => $now->copy()->subDays(4 - $i),
            ]);
        }
    }
}
