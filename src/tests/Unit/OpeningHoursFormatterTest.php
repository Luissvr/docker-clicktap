<?php

namespace Tests\Unit;

use App\Support\Research\OpeningHoursFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpeningHoursFormatterTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function schedules(): array
    {
        return [
            'lunes a sábado igual' => [
                ['lunes' => '8 a. m.–7 p. m.', 'martes' => '8 a. m.–7 p. m.', 'miércoles' => '8 a. m.–7 p. m.', 'jueves' => '8 a. m.–7 p. m.', 'viernes' => '8 a. m.–7 p. m.', 'sábado' => '8 a. m.–7 p. m.'],
                'Lunes-Sábado 8am - 7pm',
            ],
            'toda la semana igual' => [
                ['lunes' => '9–5 p. m.', 'martes' => '9–5 p. m.', 'miércoles' => '9–5 p. m.', 'jueves' => '9–5 p. m.', 'viernes' => '9–5 p. m.', 'sábado' => '9–5 p. m.', 'domingo' => '9–5 p. m.'],
                'Lunes-Domingo 9am - 5pm',
            ],
            'minutos y tramo sin sufijo inicial' => [
                ['lunes' => '11 a. m.–8:30 p. m.', 'domingo' => '12–6:30 p. m.'],
                "Lunes 11am - 8:30pm\nDomingo 12pm - 6:30pm",
            ],
            'días no consecutivos con el mismo horario no se unen' => [
                ['lunes' => '10 a. m.–6 p. m.', 'martes' => '10 a. m.–6 p. m.', 'jueves' => '10 a. m.–6 p. m.'],
                "Lunes-Martes 10am - 6pm\nJueves 10am - 6pm",
            ],
            'un día cerrado corta el grupo' => [
                ['lunes' => '9 a. m.–6 p. m.', 'miércoles' => '9 a. m.–6 p. m.'],
                "Lunes 9am - 6pm\nMiércoles 9am - 6pm",
            ],
            'dos tramos en un día' => [
                ['lunes' => '9 a. m.–1 p. m. / 3–8 p. m.', 'martes' => '9 a. m.–1 p. m. / 3–8 p. m.'],
                'Lunes-Martes 9am - 1pm / 3pm - 8pm',
            ],
            'formato que no se entiende se conserva' => [
                ['lunes' => 'Abierto las 24 horas', 'martes' => 'Abierto las 24 horas'],
                'Lunes-Martes Abierto las 24 horas',
            ],
            'vacío' => [[], ''],
        ];
    }

    /**
     * @param  array<string, string>  $hours
     */
    #[DataProvider('schedules')]
    public function test_it_groups_consecutive_days_with_the_same_schedule(array $hours, string $expected): void
    {
        $this->assertSame($expected, (new OpeningHoursFormatter)->format($hours));
    }
}
