<?php

namespace MedSales\PHPStan\Rules;

use Illuminate\Support\Facades\DB;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bane atalhos que pulam o escopo de tenant. Exceções só por arquivo, no phpstan.neon.
 *
 * @implements Rule<CallLike>
 */
final class ForbidTenantBypassRule implements Rule
{
    private const METHODS = ['withoutglobalscope', 'withoutglobalscopes', 'withoutglobalscopesexcept', 'newquerywithoutscopes', 'newquerywithoutscope', 'newmodelquery', 'getquery', 'newbasequerybuilder'];

    private const DB_ALLOWED = ['transaction', 'begintransaction', 'commit', 'rollback', 'aftercommit', 'transactionlevel'];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall) || ! $node->name instanceof Identifier) {
            return [];
        }

        $method = $node->name->toLowerString();

        if (in_array($method, self::METHODS, true)) {
            return [RuleErrorBuilder::message("{$node->name->name}() pula o escopo de tenant e é proibido.")->identifier('medsales.tenantBypass')->build()];
        }

        if ($node instanceof StaticCall && $node->class instanceof Name
            && in_array($scope->resolveName($node->class), [DB::class, 'DB'], true)
            && ! in_array($method, self::DB_ALLOWED, true)) {
            return [RuleErrorBuilder::message("DB::{$node->name->name}() não passa pelo escopo de tenant; use um model.")->identifier('medsales.tenantBypass')->build()];
        }

        return [];
    }
}
