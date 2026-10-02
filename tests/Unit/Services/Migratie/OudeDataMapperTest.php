<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Migratie;

use App\Services\Migratie\OudeDataMapper;
use PHPUnit\Framework\TestCase;

class OudeDataMapperTest extends TestCase
{
    private OudeDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new OudeDataMapper;
    }

    public function test_geslacht_nul_is_man_en_een_is_vrouw(): void
    {
        $this->assertSame('m', $this->mapper->geslacht(0));
        $this->assertSame('v', $this->mapper->geslacht(1));
        $this->assertSame('o', $this->mapper->geslacht(9));
    }

    public function test_functie_naar_rol_en_spreekniveau(): void
    {
        $predikant = $this->mapper->identiteit(1, false);
        $this->assertSame(['gebruiker'], $predikant['rollen']);
        $this->assertSame('predikant', $predikant['functie']);
        $this->assertSame('predikant', $predikant['spreekniveau']);
        $this->assertFalse($predikant['landelijk_actief']);

        $emeritus = $this->mapper->identiteit(2, false);
        $this->assertSame('predikant', $emeritus['functie']);

        $landelijk = $this->mapper->identiteit(4, false);
        $this->assertSame('spreker', $landelijk['functie']);
        $this->assertTrue($landelijk['landelijk_actief']);
        $this->assertSame('spreker', $landelijk['spreekniveau']);

        $unie = $this->mapper->identiteit(3, false);
        $this->assertTrue($unie['landelijk_actief']);

        $lokaal = $this->mapper->identiteit(6, false);
        $this->assertSame('spreker', $lokaal['functie']);
        $this->assertFalse($lokaal['landelijk_actief']);

        $contact = $this->mapper->identiteit(7, true);
        $this->assertSame(['gebruiker', 'admin'], $contact['rollen']);
        $this->assertSame('contactpersoon', $contact['functie']);
        $this->assertNull($contact['spreekniveau']);

        $gebruiker = $this->mapper->identiteit(5, false);
        $this->assertSame(['gebruiker'], $gebruiker['rollen']);
        $this->assertNull($gebruiker['functie']);
        $this->assertNull($gebruiker['spreekniveau']);
    }

    public function test_taal_en_tijd_en_district(): void
    {
        $this->assertSame('nl', $this->mapper->userTaal('nl_NL'));
        $this->assertSame('en', $this->mapper->userTaal('en_GB'));
        $this->assertSame('nl', $this->mapper->userTaal(''));

        $this->assertSame('nl', $this->mapper->gemeenteTaal('NL'));
        $this->assertSame('en', $this->mapper->gemeenteTaal('GB'));
        $this->assertSame('pt', $this->mapper->gemeenteTaal('PRT'));
        $this->assertSame('pap', $this->mapper->gemeenteTaal('PAP'));
        $this->assertSame('gha', $this->mapper->gemeenteTaal('GHA'));
        $this->assertSame('nl', $this->mapper->gemeenteTaal(''));

        $this->assertSame('nederlands', $this->mapper->taalSlug('dutch'));
        $this->assertSame('engels', $this->mapper->taalSlug('english'));
        $this->assertSame('spaans', $this->mapper->taalSlug('spanish'));
        $this->assertSame('papiaments', $this->mapper->taalSlug('papiamentu'));
        $this->assertSame('portugees', $this->mapper->taalSlug('portuguese'));
        $this->assertNull($this->mapper->taalSlug('klingon'));

        $this->assertSame('10:15', $this->mapper->begintijd('10.15', '10:00'));
        $this->assertSame('09:30', $this->mapper->begintijd('9:30', '10:00'));
        $this->assertSame('10:00', $this->mapper->begintijd('vrijd', '10:00'));
        $this->assertSame('11:00', $this->mapper->begintijd('--.--', '11:00'));

        $this->assertSame(3, $this->mapper->districtId(0));
        $this->assertSame(1, $this->mapper->districtId(1));
        $this->assertNull($this->mapper->districtId(null));
    }

    public function test_kind_confirmed_en_preekbeurtkeuze(): void
    {
        $this->assertSame(4, $this->mapper->bijzonderheidId(4));
        $this->assertNull($this->mapper->bijzonderheidId(0));
        $this->assertNull($this->mapper->bijzonderheidId(null));

        $this->assertSame(1, $this->mapper->bevestigd(1));
        $this->assertNull($this->mapper->bevestigd(0));

        $gekozen = $this->mapper->kiesPreekbeurt([
            ['id' => 10, 'kind' => 0],
            ['id' => 3, 'kind' => 4],
            ['id' => 8, 'kind' => 11],
        ]);
        $this->assertSame(8, $gekozen['id']);

        $zonderKind = $this->mapper->kiesPreekbeurt([
            ['id' => 2, 'kind' => 0],
            ['id' => 9, 'kind' => null],
        ]);
        $this->assertSame(9, $zonderKind['id']);
    }

    public function test_lege_naam_en_email(): void
    {
        $this->assertSame('Onbekend', $this->mapper->naam(''));
        $this->assertSame('Onbekend', $this->mapper->naam(null));
        $this->assertSame('Jan', $this->mapper->naam('Jan'));
        $this->assertSame('user_12@migratie.local', $this->mapper->email(null, 12));
        $this->assertSame('a@b.c', $this->mapper->email('a@b.c', 12));
    }

    public function test_dienst_taal(): void
    {
        $this->assertSame('nl', $this->mapper->dienstTaal(null));
        $this->assertSame('nl', $this->mapper->dienstTaal('dutch'));
        $this->assertSame('en', $this->mapper->dienstTaal('english'));
        $this->assertSame('pap', $this->mapper->dienstTaal('papiamentu'));
        $this->assertSame('pt', $this->mapper->dienstTaal('portuguese'));
        $this->assertSame('es', $this->mapper->dienstTaal('spanish'));
        $this->assertSame('gha', $this->mapper->dienstTaal('ghanaian'));
    }
}
