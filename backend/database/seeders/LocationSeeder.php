<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Region;
use App\Models\District;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            'Qoraqalpog‘iston Respublikasi' => ['Amudaryo tumani', 'Beruniy tumani', 'Kegayli tumani', 'Qonliko‘l tumani', 'Qorao‘zak tumani', 'Qo‘ng‘irot tumani', 'Mo‘ynoq tumani', 'Nukus tumani', 'Nukus shahri', 'Taxtako‘pir tumani', 'To‘rtko‘l tumani', 'Xo‘jayli tumani', 'Chimboy tumani', 'Shumanay tumani', 'Ellikqal‘a tumani', 'Taxiatosh shahri', 'Bo‘zatov tumani'],
            'Andijon viloyati' => ['Andijon shahri', 'Andijon tumani', 'Asaka tumani', 'Baliqchi tumani', 'Buloqboshi tumani', 'Bo‘z tumani', 'Jalaquduq tumani', 'Izbosgan tumani', 'Qorasuv shahri', 'Qo‘rg‘ontepa tumani', 'Marhamat tumani', 'Oltinko‘l tumani', 'Paxtaobod tumani', 'Ulug‘nor tumani', 'Xonabod shahri', 'Xo‘jaobod tumani', 'Shahrixon tumani', 'Asaka shahri'],
            'Buxoro viloyati' => ['Buxoro shahri', 'Buxoro tumani', 'Vobkent tumani', 'G‘ijduvon tumani', 'Jondor tumani', 'Kogon tumani', 'Kogon shahri', 'Qorako‘l tumani', 'Qorovulbozor tumani', 'Olot tumani', 'Peshku tumani', 'Romitan tumani', 'Shofirkon tumani'],
            'Jizzax viloyati' => ['Arnasoy tumani', 'Baxmal tumani', 'G‘allaorol tumani', 'Do‘stlik tumani', 'Sh.Rashidov tumani', 'Jizzax shahri', 'Zarbdor tumani', 'Zafarobod tumani', 'Zomin tumani', 'Mirzacho‘l tumani', 'Paxtakor tumani', 'Forish tumani', 'Yangiobod tumani'],
            'Qashqadaryo viloyati' => ['G‘uzor tumani', 'Dehqonobod tumani', 'Qamashi tumani', 'Qarshi tumani', 'Qarshi shahri', 'Kasbi tumani', 'Kitob tumani', 'Koson tumani', 'Mirishkor tumani', 'Muborak tumani', 'Nishon tumani', 'Chiroqchi tumani', 'Shahrisabz tumani', 'Yakkabog‘ tumani', 'Shahrisabz shahri', 'Ko‘kdala tumani'],
            'Navoiy viloyati' => ['Zarafshon shahri', 'Karmana tumani', 'Qiziltepa tumani', 'Konimex tumani', 'Navbahor tumani', 'Navoiy shahri', 'Nurota tumani', 'Tomdi tumani', 'Uchquduq tumani', 'Xatirchi tumani', 'G‘ozg‘on shahri'],
            'Namangan viloyati' => ['Kosonsoy tumani', 'Mingbuloq tumani', 'Namangan tumani', 'Namangan shahri', 'Norin tumani', 'Pop tumani', 'To‘raqo‘rg‘on tumani', 'Uychi tumani', 'Uchqo‘rg‘on tumani', 'Chortoq tumani', 'Chust tumani', 'Yangiqo‘rg‘on tumani', 'Davlatobod tumani'],
            'Samarqand viloyati' => ['Bulung‘ur tumani', 'Jomboy tumani', 'Ishtixon tumani', 'Kattaqo‘rg‘on tumani', 'Kattaqo‘rg‘on shahri', 'Qo‘shrabot tumani', 'Narpay tumani', 'Nurabod tumani', 'Oqdaryo tumani', 'Payariq tumani', 'Pastarg‘om tumani', 'Paxtachi tumani', 'Samarqand tumani', 'Samarqand shahri', 'Toyloq tumani', 'Urgut tumani'],
            'Surxandaryo viloyati' => ['Angor tumani', 'Boysun tumani', 'Denov tumani', 'Jarqo‘rg‘on tumani', 'Qiziriq tumani', 'Qo‘mqo‘rg‘on tumani', 'Muzrabot tumani', 'Oltinsoy tumani', 'Sariosiyo tumani', 'Termiz tumani', 'Termiz shahri', 'Uzun tumani', 'Sherobod tumani', 'Sho‘rchi tumani', 'Bandixon tumani'],
            'Sirdaryo viloyati' => ['Boyovut tumani', 'Guliston tumani', 'Guliston shahri', 'Mirzaobod tumani', 'Oqoltin tumani', 'Sayxunobod tumani', 'Sardoba tumani', 'Sirdaryo tumani', 'Xavos tumani', 'Shirin shahri', 'Yangiyer shahri'],
            'Toshkent viloyati' => ['Angiren shahri', 'Bekabod tumani', 'Bekabod shahri', 'Bo‘ka tumani', 'Bo‘stonliq tumani', 'Zangiota tumani', 'Qibray tumani', 'Quyichirchiq tumani', 'Oqqo‘rg‘on tumani', 'Olmaliq shahri', 'Ohangaron tumani', 'Parkent tumani', 'Piskent tumani', 'O‘rtachirchiq tumani', 'Chinoz tumani', 'Chirchiq shahri', 'Yuqorichirchiq tumani', 'Yangiyo‘l tumani', 'Ohangaron shahri', 'Yangiyo‘l shahri', 'Toshkent tumani'],
            'Farg‘ona viloyati' => ['Beshariq tumani', 'Bog‘dod tumani', 'Buvayda tumani', 'Dang‘ara tumani', 'Yozyovon tumani', 'Quva tumani', 'Quvasoy shahri', 'Qo‘qon shahri', 'Qo‘shtepa tumani', 'Marg‘ilon shahri', 'Oltiariq tumani', 'Rishton tumani', 'So‘x tumani', 'Toshloq tumani', 'Uchko‘prik tumani', 'O‘zbekiston tumani', 'Farg‘ona tumani', 'Farg‘ona shahri', 'Furqat tumani'],
            'Xorazm viloyati' => ['Bog‘ot tumani', 'Gurlan tumani', 'Qo‘shko‘pir tumani', 'Urganch tumani', 'Urganch shahri', 'Xiva tumani', 'Xazarasp tumani', 'Xonqa tumani', 'Shavot tumani', 'Yangiariq tumani', 'Yangibozor tumani', 'Xiva shahri', 'Do\'stlik shahri', 'Tuproqqala tumani'],
            'Toshkent shahri' => ['Bektimer tumani', 'Mirzo Ulug\'bek tumani', 'Mirobod tumani', 'Olmazor tumani', 'Sirg\'ali tumani', 'Uchtepa tumani', 'Yashnobod tumani', 'Chilonzor tumani', 'Shayxontohur tumani', 'Yunusobod tumani', 'Yakkasaroy tumani', 'Yangihayot tumani'],
        ];

        // Safe to re-run on a live database: it only adds the regions and districts that are missing and never deletes or
        // renames a row (branches and addresses point at them; the old TRUNCATE ... CASCADE emptied those tables too).
        // Only rows it creates get the launch defaults (Andijon viloyati / Asaka tumani active), so whatever was switched
        // on or off in the control center (Tizim → Viloyatlar va tumanlar) stays as it is.
        DB::transaction(function () use ($regions) {
            foreach ($regions as $regionName => $districts) {
                $region = Region::firstOrCreate(
                    ['name_uz' => $regionName],
                    ['is_active' => $regionName === 'Andijon viloyati']
                );

                foreach ($districts as $districtName) {
                    District::firstOrCreate(
                        ['region_id' => $region->id, 'name_uz' => $districtName],
                        ['is_active' => $regionName === 'Andijon viloyati' && $districtName === 'Asaka tumani']
                    );
                }
            }
        });
    }
}
