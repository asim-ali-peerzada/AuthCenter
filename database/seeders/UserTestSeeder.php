<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserTestSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonthNoOverflow();

        // Create 3 users for last month
        foreach (range(1, 3) as $i) {
            DB::table('users')->insert([
                'uuid' => Str::uuid(),
                'name' => "Old User $i",
                'email' => "olduser$i@example.com",
                'status' => 'active',
                'password' => bcrypt('password'),
                'created_at' => $lastMonth->copy()->addDays($i),
                'updated_at' => $lastMonth->copy()->addDays($i),
            ]);
        }

        // Create 5 users for this month
        foreach (range(1, 5) as $i) {
            DB::table('users')->insert([
                'uuid' => Str::uuid(),
                'name' => "New User $i",
                'email' => "newuser$i@example.com",
                'status' => 'active',
                'password' => bcrypt('password'),
                'created_at' => $now->copy()->subDays(5 - $i),
                'updated_at' => $now->copy()->subDays(5 - $i),
            ]);
        }
    }
}

