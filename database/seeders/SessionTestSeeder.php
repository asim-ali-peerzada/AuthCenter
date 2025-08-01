<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SessionTestSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonthNoOverflow();

        foreach (range(1, 3) as $i) {
            DB::table('sessions')->insert([
                'id' => Str::random(40),
                'user_id' => rand(1, 10), // Update according to your seeded user IDs
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SeederBot',
                'payload' => 'test',
                'last_activity' => $lastMonth->copy()->addDays($i)->timestamp,
            ]);
        }

        foreach (range(1, 6) as $i) {
            DB::table('sessions')->insert([
                'id' => Str::random(40),
                'user_id' => rand(1, 10),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SeederBot',
                'payload' => 'test',
                'last_activity' => $now->copy()->subDays(6 - $i)->timestamp,
            ]);
        }
    }
}

