<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2022 David Young
 * @license   https://github.com/aphiria/aphiria/blob/1.x/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Authorization;

use InvalidArgumentException;

/**
 * Defines an authorization policy
 */
class AuthorizationPolicy
{
    /** @var list<object> The list of requirements */
    public readonly array $requirements;
    /** @var list<string>|null The list of authentication schemes the requirements are evaluated against, or null if using the default scheme */
    public readonly ?array $authenticationSchemeNames;

    /**
     * @param string $name The name of the policy
     * @param list<object>|object $requirements The requirement or list of requirements
     * @param list<string>|string|null $authenticationSchemeNames The authentication scheme name or list of scheme names the requirements are evaluated against, or null if using the default scheme
     * @throws InvalidArgumentException Thrown if the requirements were empty
     */
    public function __construct(
        public readonly string $name,
        array|object $requirements,
        array|string $authenticationSchemeNames = null
    ) {
        if (!\is_array($requirements)) {
            $requirements = [$requirements];
        }

        if (\count($requirements) === 0) {
            throw new InvalidArgumentException('Requirements cannot be empty');
        }

        if (\is_string($authenticationSchemeNames)) {
            $authenticationSchemeNames = [$authenticationSchemeNames];
        }

        $this->authenticationSchemeNames = $authenticationSchemeNames;
        $this->requirements = $requirements;
    }
}
