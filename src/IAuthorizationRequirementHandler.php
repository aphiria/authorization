<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2021 David Young
 * @license   https://github.com/aphiria/aphiria/blob/1.x/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Authorization;

use Aphiria\Security\IPrincipal;
use InvalidArgumentException;

/**
 * Defines the interface for authorization requirement handlers to implement
 *
 * @template T of object
 */
interface IAuthorizationRequirementHandler
{
    /**
     * Handles an authorization requirement
     *
     * @param IPrincipal $user The user to authorize
     * @param T $requirement The requirement to handle
     * @param AuthorizationContext $authorizationContext The current authorization context
     * @throws InvalidArgumentException Thrown if the requirement was of the incorrect type
     */
    public function handle(IPrincipal $user, object $requirement, AuthorizationContext $authorizationContext): void;
}
