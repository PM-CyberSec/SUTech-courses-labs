<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      DB::table('tags')->insert([
        ['name' => 'Premium'],
        ['name' => 'Wireless'],
        ['name' => 'Professional'],
        ['name' => 'Gaming'],
        ['name' => 'Smart'],
        ['name' => 'Portable'],
        ['name' => '4K'],
        ['name' => 'Bluetooth'],
        ['name' => 'Noise Cancelling'],
        ['name' => 'Apple'],
        ['name' => 'Android'],
        ['name' => 'Energy Saving'],
        ['name' => 'Touchscreen'],
        ['name' => 'LED'],
        ['name' => 'OLED'],
        ['name' => 'Mirrorless'],
        ['name' => 'DSLR'],
        ['name' => 'Home Appliance'],
        ['name' => 'Fast Charging'],
        ['name' => 'USB-C'],
        ['name' => 'Compact'],
        ['name' => 'Ultra HD'],
        ['name' => 'Smart Home'],
        ['name' => 'Office'],
        ['name' => 'Student'],
        ]);
    }
}
