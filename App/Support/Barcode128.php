<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Barcode128
{
    private const PATTERNS = <<<'PATTERNS'
212222 222122 222221 121223 121322 131222 122213 122312 132212 221213 221312 231212
112232 122132 122231 113222 123122 123221 223211 221132 221231 213212 223112 312131
311222 321122 321221 312212 322112 322211 212123 212321 232121 111323 131123 131321
112313 132113 132311 211313 231113 231311 112133 112331 132131 113123 113321 133121
313121 211331 231131 213113 213311 213131 311123 311321 331121 312113 312311 332111
314111 221411 431111 111224 111422 121124 121421 141122 141221 112214 112412 122114
122411 142112 142211 241211 221114 413111 241112 134111 111242 121142 121241 114212
124112 124211 411212 421112 421211 212141 214121 412121 111143 111341 131141 114113
114311 411113 411311 113141 114131 311141 411131 211412 211214 211232 2331112
PATTERNS;

    public static function svg(string $code): string
    {
        if (preg_match('/^IFT[0-9]{6,}$/D', $code) !== 1) {
            throw new InvalidArgumentException(
                'Invalid student barcode.'
            );
        }

        $patterns = preg_split(
            '/\s+/',
            trim(self::PATTERNS)
        );

        $symbols = [104]; // Start Code B
        $checksum = 104;

        for ($i = 0; $i < strlen($code); $i++) {
            $symbol = ord($code[$i]) - 32;

            $symbols[] = $symbol;
            $checksum += $symbol * ($i + 1);
        }

        $symbols[] = $checksum % 103;
        $symbols[] = 106; // Stop

        $x = 20;
        $bars = '';

        foreach ($symbols as $symbol) {
            $pattern = $patterns[$symbol];

            for ($i = 0; $i < strlen($pattern); $i++) {
                $width = (int) $pattern[$i] * 2;

                if ($i % 2 === 0) {
                    $bars .= '<rect x="' . $x
                        . '" y="8" width="' . $width
                        . '" height="64"/>';
                }

                $x += $width;
            }
        }

        $totalWidth = $x + 20;

        $label = htmlspecialchars(
            $code,
            ENT_QUOTES,
            'UTF-8'
        );

        return '<svg xmlns="http://www.w3.org/2000/svg"'
            . ' width="' . $totalWidth . '" height="80"'
            . ' viewBox="0 0 ' . $totalWidth . ' 80"'
            . ' role="img" aria-label="Barcode ' . $label . '"'
            . ' shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<g fill="#111">' . $bars . '</g>'
            . '</svg>';
    }
}
