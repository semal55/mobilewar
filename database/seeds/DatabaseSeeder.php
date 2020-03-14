<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(GameResourceSeeder::class);
        $this->call(MapFieldTypeSeeder::class);
        $this->call(MapSeeder::class);
        $this->call(FarmSeeder::class);
        $this->call(BuildSeeder::class);
    }
}
