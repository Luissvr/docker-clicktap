<?php

namespace App\Support\Research;

use Illuminate\Support\Str;

/**
 * Convierte el horario por día de Google en líneas compactas: «Lunes-Sábado 8am - 7pm».
 */
class OpeningHoursFormatter
{
    private const DAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    private const TIME = '/^(\d{1,2})(?::(\d{2}))?\s*(?:([ap])\.?\s*m\.?)?$/iu';

    /**
     * @param  array<string, string>  $hours  día (en minúsculas) => rango tal como lo entrega Google
     */
    public function format(array $hours): string
    {
        $ranges = [];
        foreach (self::DAYS as $index => $day) {
            if (isset($hours[$day]) && trim($hours[$day]) !== '') {
                $ranges[$index] = $this->normalizeRanges($hours[$day]);
            }
        }

        $lines = [];
        $group = null;
        foreach ($ranges as $index => $range) {
            if ($group !== null && $group['last'] === $index - 1 && $group['range'] === $range) {
                $group['last'] = $index;

                continue;
            }
            if ($group !== null) {
                $lines[] = $this->line($group);
            }
            $group = ['first' => $index, 'last' => $index, 'range' => $range];
        }
        if ($group !== null) {
            $lines[] = $this->line($group);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{first: int, last: int, range: string}  $group
     */
    private function line(array $group): string
    {
        $first = Str::ucfirst(self::DAYS[$group['first']]);
        $days = $group['first'] === $group['last'] ? $first : $first.'-'.Str::ucfirst(self::DAYS[$group['last']]);

        return $days.' '.$group['range'];
    }

    /**
     * Un día puede tener varios tramos separados por « / ».
     */
    private function normalizeRanges(string $ranges): string
    {
        return collect(explode(' / ', $ranges))
            ->map(fn (string $period): string => $this->normalizePeriod($period))
            ->implode(' / ');
    }

    private function normalizePeriod(string $period): string
    {
        $period = trim($period);
        $parts = preg_split('/\s*[–—-]\s*/u', $period);
        if (count($parts) !== 2 || ! preg_match(self::TIME, $parts[1], $end) || ! preg_match(self::TIME, $parts[0], $start)) {
            return $period;
        }

        $suffix = fn (array $match): ?string => isset($match[3]) && $match[3] !== '' ? strtolower($match[3]).'m' : null;
        $endSuffix = $suffix($end);
        $startSuffix = $suffix($start);

        if ($startSuffix === null && $endSuffix !== null) {
            // Google omite el sufijo del inicio cuando coincide con el del cierre («12–6:30 p. m.»);
            // si así el inicio quedara después del cierre («9–5 p. m.»), es la mañana.
            $startSuffix = $this->minutes($start, $endSuffix) < $this->minutes($end, $endSuffix) ? $endSuffix : 'am';
        }

        return $this->time($start, $startSuffix).' - '.$this->time($end, $endSuffix);
    }

    /**
     * @param  array<int, string>  $match
     */
    private function minutes(array $match, ?string $suffix): int
    {
        $hour = (int) $match[1] % 12 + ($suffix === 'pm' ? 12 : 0);

        return $hour * 60 + (int) ($match[2] ?? 0);
    }

    /**
     * @param  array<int, string>  $match
     */
    private function time(array $match, ?string $suffix): string
    {
        $minutes = isset($match[2]) && $match[2] !== '' && $match[2] !== '00' ? ':'.$match[2] : '';

        return (int) $match[1].$minutes.($suffix ?? '');
    }
}
