<?php

use App\Core\Tenancy\Subdomain;

it('valida subdomínios', function (string $value, bool $expected) {
    expect(Subdomain::isValid($value))->toBe($expected);
})->with([
    ['mychelleamaral', true], ['a-1', true], [str_repeat('a', 63), true],
    ['ab', false], [str_repeat('a', 64), false], ['-abc', false], ['abc-', false], ['Abc', false],
    ['a.bc', false], ['a_bc', false], ["abc\n", false], ['xn--abc', false], ['www', false], ['financeiro', false],
]);
