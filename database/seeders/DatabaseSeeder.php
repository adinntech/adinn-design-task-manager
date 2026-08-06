<?php
namespace Database\Seeders;
use App\Models\User; use Illuminate\Database\Seeder; use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {public function run():void {User::updateOrCreate(['email'=>'bd@adinn.com'],['name'=>'Demo BD','password'=>Hash::make('Password@123'),'role'=>'bd','is_active'=>true]);foreach(['Aarav Designer','Meera Designer','Kavin Designer'] as $i=>$name){User::updateOrCreate(['email'=>'designer'.($i+1).'@adinn.com'],['name'=>$name,'password'=>Hash::make('Password@123'),'role'=>'designer','is_active'=>true]);}User::updateOrCreate(['email'=>'head@adinn.com'],['name'=>'Designer Head','password'=>Hash::make('Password@123'),'role'=>'designer_head','is_active'=>true]);}}
