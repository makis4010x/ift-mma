<?php

declare(strict_types=1);

use App\Support\MembershipCoverageService as Coverage;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// No database connection: calculation tests only.
require_once dirname(__DIR__)
    . '/App/Support/MembershipCoverageService.php';

$passed = 0;
$failures = 0;

$check = static function (
    string $name,
    mixed $actual,
    mixed $expected
) use (&$passed, &$failures): void {
    if ($actual === $expected) {
        $passed++;
        echo "[OK] {$name}" . PHP_EOL;
    } else {
        $failures++;
        echo "[FAIL] {$name}" . PHP_EOL;
        echo '  Actual: '
            . var_export($actual, true)
            . PHP_EOL;
        echo '  Expected: '
            . var_export($expected, true)
            . PHP_EOL;
    }
};

$reject = static function (
    string $name,
    callable $test
) use ($check): void {
    try {
        $test();
        $check($name, false, true);
    } catch (DomainException $exception) {
        $check($name, true, true);
    }
};

$check(
    '45,00 = 4500 cents',
    Coverage::cents('45,00'),
    4500
);

$check(
    '45.5 = 4550 cents',
    Coverage::cents('45.5'),
    4550
);

$check(
    'Two months = 90 EUR',
    Coverage::cents('45') * 2,
    9000
);

$reject(
    'Reject negative amount',
    fn() => Coverage::cents('-45')
);

$reject(
    'Reject three decimal places',
    fn() => Coverage::cents('45.001')
);

$check(
    'On-time renewal',
    Coverage::decideCoverage(
        '2026-10-26',
        '2026-10-26',
        []
    ),
    ['start' => '2026-10-26', 'decision' => 'on_time']
);

$check(
    'Early payment continues next period',
    Coverage::decideCoverage(
        '2026-11-26',
        '2026-10-26',
        []
    ),
    ['start' => '2026-11-26', 'decision' => 'advance']
);

$check(
    'Late without attendance moves date',
    Coverage::decideCoverage(
        '2026-10-26',
        '2026-10-29',
        []
    ),
    ['start' => '2026-10-29', 'decision' => 'shifted']
);

$check(
    'Late with prior attendance keeps date',
    Coverage::decideCoverage(
        '2026-10-26',
        '2026-10-29',
        ['2026-10-27']
    ),
    ['start' => '2026-10-26', 'decision' => 'attended']
);

$check(
    'Only payment-day scan does not backdate',
    Coverage::decideCoverage(
        '2026-10-26',
        '2026-10-29',
        ['2026-10-29']
    ),
    ['start' => '2026-10-29', 'decision' => 'shifted']
);

$check(
    'Long absence restarts without debts',
    Coverage::decideCoverage(
        '2026-01-26',
        '2026-10-26',
        []
    ),
    ['start' => '2026-10-26', 'decision' => 'restart']
);

$reject(
    'Long gap with attendance needs review',
    fn() => Coverage::decideCoverage(
        '2026-01-26',
        '2026-03-01',
        ['2026-02-01']
    )
);

$check(
    'Two months cross year',
    Coverage::monthBoundary('2026-11-26', 2),
    '2027-01-26'
);

$check(
    'January 31 to February',
    Coverage::monthBoundary('2027-01-31', 1),
    '2027-02-28'
);

$check(
    'Original day restored in March',
    Coverage::monthBoundary('2027-01-31', 2),
    '2027-03-31'
);

$check(
    'Leap-year February',
    Coverage::monthBoundary('2028-01-31', 1),
    '2028-02-29'
);

$reject(
    'Invalid date rejected',
    fn() => Coverage::monthBoundary('2026-02-30', 1)
);

$periods = [
    [
        'start' => '2026-10-26',
        'end' => '2026-11-26'
    ],
    [
        'start' => '2026-11-26',
        'end' => '2026-12-26'
    ]
];

$check(
    'Consecutive prepaid periods continue at their end',
    Coverage::renewalCursor(
        $periods,
        '2026-10-26',
        '2026-10-26'
    )['expected'],
    '2026-12-26'
);

$check(
    'No valid periods: ignore cancelled history',
    Coverage::renewalCursor(
        [],
        '2026-10-26',
        '2026-10-26'
    )['expected'],
    '2026-10-26'
);

$check(
    'Future period does not hide a current gap',
    Coverage::renewalCursor(
        [$periods[1]],
        '2026-10-26',
        '2026-10-26'
    )['expected'],
    '2026-10-26'
);

$reject(
    'Overlapping paid periods require review',
    fn() => Coverage::renewalCursor(
        [
            [
                'start' => '2026-10-26',
                'end' => '2026-11-26'
            ],
            [
                'start' => '2026-11-01',
                'end' => '2026-12-01'
            ]
        ],
        '2026-10-26',
        '2026-10-26'
    )
);

$check(
    'Original end-of-month boundary used for late test',
    Coverage::decideCoverage(
        '2027-02-28',
        '2027-03-29',
        ['2027-03-01'],
        '2027-03-31'
    ),
    ['start' => '2027-02-28', 'decision' => 'attended']
);

printf(
    "\n%d passed; %d failed. No database changes.\n",
    $passed,
    $failures
);

exit($failures === 0 ? 0 : 1);
