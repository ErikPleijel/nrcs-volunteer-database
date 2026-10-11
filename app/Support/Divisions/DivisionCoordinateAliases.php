<?php

namespace App\Support\Divisions;

/**
 * Matching an LGA coordinates row (name + state, from the lgas.csv data) to
 * a division: state names to branch names, LGA spellings to the division
 * spellings used in this database, and a normalised key that ignores case,
 * spaces and punctuation. Shared by DivisionCoordinatesSeeder and
 * divisions:import-geo so the two can never disagree.
 *
 * An alias is only needed where the spellings truly differ; differences in
 * case or punctuation are already absorbed by key().
 */
final class DivisionCoordinateAliases
{
    private const BRANCH = [
        'federal capital territory' => 'FCT',
        'f.c.t.' => 'FCT',
        'abuja fct' => 'FCT',
    ];

    /** LGA spelling (lowercase) => division name, in any state. */
    private const DIVISION = [
        'southern ijaw' => 'Southern Jaw',
        'yenagoa' => 'Yenegoa',
        'otukpo' => 'Oturkpo',
        'bekwarra' => 'Bekwara',
        'orhionmwon' => 'Orhionwon',
        'ado-ekiti' => 'Ado',
        'emure' => 'Emure/Ise/Orun',
        'irepodun/ifelodun' => 'Irepodun',
        'igbo etiti' => 'Igbo-Ekiti',
        'shongom' => 'Shomgom',
        'ezinihitte mbaise' => 'Ezinihitte',
        'birnin kudu' => 'Birni Kudu',
        'kiri kasama' => 'Kiri Kasamma',
        'birnin gwari' => 'Birni-Gwari',
        'zangon kataf' => 'Zango-Kataf',
        'garun malam' => 'Garum Malam',
        'matazu' => 'Matazuu',
        'aliero' => 'Aleiro',
        'danko-wasagu' => 'Wasagu/Danko',
        'ogori/magongo' => 'Ogori/Mangongo',
        'ifako-ijaiye' => 'Ifako-Ijaye',
        'somolu' => 'Shomolu',
        'nasarawa egon' => 'Nasarawa-Eggon',
        'ayedaade' => 'Aiyedade',
        'atakunmosa east' => 'Atakumosa East',
        'atakunmosa west' => 'Atakumosa West',
        'ilesa east' => 'Ilesha East',
        'ilesa west' => 'Ilesha West',
        'ogbomosho south' => 'Ogbmosho South',
        'oorelope' => 'Orelope',
        'ogbomosho north' => 'Ogbomoso North',
        'barkin ladi' => 'Barikin Ladi',
        'isuikwuato' => 'Isuikwato',
        'warri south west' => 'Warri-South West',
        'obio/akpor' => 'Obia/Akpor',
        'omuma' => 'Omumma',
        'birnin magaji/kiyaw' => 'Birnin Magaji',
    ];

    /** [state (lowercase), LGA spelling (lowercase)] => division name, in that state only. */
    private const DIVISION_IN_STATE = [
        // Kano's LGA is spelled Nassarawa here; Nasarawa State's own
        // Nasarawa LGA keeps its name.
        'kano|nasarawa' => 'Nassarawa',
        'federal capital territory|abuja' => 'Abuja Municipal',
    ];

    /** The branch name for a coordinates row's state. */
    public static function branch(string $stateName): string
    {
        return self::BRANCH[strtolower(trim($stateName))] ?? $stateName;
    }

    /** The division name for a coordinates row's LGA in $stateName. */
    public static function division(string $name, string $stateName): string
    {
        $key = strtolower(trim($name));
        $stateKey = strtolower(trim($stateName));

        return self::DIVISION_IN_STATE["{$stateKey}|{$key}"] ?? self::DIVISION[$key] ?? $name;
    }

    /** Lowercase, letters and digits only: "Enugu South," and "enugu south" match. */
    public static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/u', '', mb_strtolower($name)) ?? '';
    }
}
