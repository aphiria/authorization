<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2022 David Young
 * @license   https://github.com/aphiria/aphiria/blob/1.x/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Authorization\Tests\Middleware;

use Aphiria\Authentication\IAuthenticator;
use Aphiria\Authentication\IUserAccessor;
use Aphiria\Authorization\AuthorizationPolicy;
use Aphiria\Authorization\AuthorizationPolicyRegistry;
use Aphiria\Authorization\AuthorizationResult;
use Aphiria\Authorization\IAuthority;
use Aphiria\Authorization\Middleware\Authorize;
use Aphiria\Authorization\RequirementHandlers\RolesRequirement;
use Aphiria\Net\Http\HttpStatusCode;
use Aphiria\Net\Http\IRequest;
use Aphiria\Net\Http\IRequestHandler;
use Aphiria\Net\Http\IResponse;
use Aphiria\Security\IIdentity;
use Aphiria\Security\IPrincipal;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthorizeTest extends TestCase
{
    private Authorize $middleware;
    private IAuthority&MockObject $authority;
    private IAuthenticator&MockObject $authenticator;
    private AuthorizationPolicyRegistry $policies;
    private IUserAccessor&MockObject $userAccessor;

    protected function setUp(): void
    {
        $this->authority = $this->createMock(IAuthority::class);
        $this->authenticator = $this->createMock(IAuthenticator::class);
        $this->policies = new AuthorizationPolicyRegistry();
        $this->userAccessor = $this->createMock(IUserAccessor::class);
        $this->middleware = new Authorize($this->authority, $this->authenticator, $this->policies, $this->userAccessor);
    }

    public function getUnauthenticatedUsers(): array
    {
        $userWithNoIdentity = $this->createMock(IPrincipal::class);
        $userWithNoIdentity->method('getPrimaryIdentity')
            ->willReturn(null);
        $userWithUnauthenticatedIdentity = $this->createMock(IPrincipal::class);
        $unauthenticatedIdentity = $this->createMock(IIdentity::class);
        $unauthenticatedIdentity->method('isAuthenticated')
            ->willReturn(false);
        $userWithUnauthenticatedIdentity->method('getPrimaryIdentity')
            ->willReturn($unauthenticatedIdentity);

        return [
            [$userWithNoIdentity],
            [$userWithUnauthenticatedIdentity]
        ];
    }

    public function getInvalidParameters(): array
    {
        return [
            [null, null, 'Either the policy name or the policy must be set'],
            ['policy', new AuthorizationPolicy('foo', [new RolesRequirement('admin')]), 'Either the policy name or the policy must be set']
        ];
    }

    public function testHandlingAuthorizedResultForPolicyCallsNextRequestHandler(): void
    {
        $request = $this->createMock(IRequest::class);
        $user = $this->createMockAuthenticatedUser();
        $this->userAccessor->expects($this->once())
            ->method('getUser')
            ->with($request)
            ->willReturn($user);
        $policy = new AuthorizationPolicy('policy', [$this]);
        $this->authority->expects($this->once())
            ->method('authorize')
            ->with($user, $policy)
            ->willReturn(AuthorizationResult::pass());
        $this->middleware->setParameters(['policy' => $policy]);
        $next = $this->createMock(IRequestHandler::class);
        $response = $this->createMock(IResponse::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);
        $this->assertSame($response, $this->middleware->handle($request, $next));
    }

    public function testHandlingAuthorizedResultForPolicyNameCallsNextRequestHandler(): void
    {
        $request = $this->createMock(IRequest::class);
        $user = $this->createMockAuthenticatedUser();
        $this->userAccessor->expects($this->once())
            ->method('getUser')
            ->with($request)
            ->willReturn($user);
        $policy = new AuthorizationPolicy('policy', [$this]);
        $this->policies->registerPolicy($policy);
        $this->authority->expects($this->once())
            ->method('authorize')
            ->with($user, $policy)
            ->willReturn(AuthorizationResult::pass());
        $this->middleware->setParameters(['policyName' => $policy->name]);
        $next = $this->createMock(IRequestHandler::class);
        $response = $this->createMock(IResponse::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);
        $this->assertSame($response, $this->middleware->handle($request, $next));
    }

    public function testHandlingUnauthorizedResultForPolicyReturnsForbiddenResponse(): void
    {
        $request = $this->createMock(IRequest::class);
        $user = $this->createMockAuthenticatedUser();
        $this->userAccessor->expects($this->once())
            ->method('getUser')
            ->with($request)
            ->willReturn($user);
        $policy = new AuthorizationPolicy('policy', [$this], 'scheme');
        $this->authority->expects($this->once())
            ->method('authorize')
            ->with($user, $policy)
            ->willReturn(AuthorizationResult::fail([$this]));
        $this->authenticator->expects($this->once())
            ->method('forbid')
            ->with($request, $this->callback(fn (IResponse $response) => $response->getStatusCode() === HttpStatusCode::Forbidden), 'scheme');
        $this->middleware->setParameters(['policy' => $policy]);
        $response = $this->middleware->handle($request, $this->createMock(IRequestHandler::class));
        $this->assertSame(HttpStatusCode::Forbidden, $response->getStatusCode());
    }

    public function testHandlingUnauthorizedResultForPolicyNameReturnsForbiddenResponse(): void
    {
        $request = $this->createMock(IRequest::class);
        $user = $this->createMockAuthenticatedUser();
        $this->userAccessor->expects($this->once())
            ->method('getUser')
            ->with($request)
            ->willReturn($user);
        $policy = new AuthorizationPolicy('policy', [$this], 'scheme');
        $this->policies->registerPolicy($policy);
        $this->authority->expects($this->once())
            ->method('authorize')
            ->with($user, $policy)
            ->willReturn(AuthorizationResult::fail([$this]));
        $this->authenticator->expects($this->once())
            ->method('forbid')
            ->with($request, $this->callback(fn (IResponse $response) => $response->getStatusCode() === HttpStatusCode::Forbidden), 'scheme');
        $this->middleware->setParameters(['policyName' => $policy->name]);
        $response = $this->middleware->handle($request, $this->createMock(IRequestHandler::class));
        $this->assertSame(HttpStatusCode::Forbidden, $response->getStatusCode());
    }

    /**
     * @dataProvider getUnauthenticatedUsers
     *
     * @param IPrincipal $user The unauthenticated user
     */
    public function testHandlingUnauthenticatedUserReturnsUnauthorizedAndChallengedResponse(IPrincipal $user): void
    {
        $request = $this->createMock(IRequest::class);
        $this->userAccessor->expects($this->once())
            ->method('getUser')
            ->with($request)
            ->willReturn($user);
        $policy = new AuthorizationPolicy('policy', [$this], 'scheme');
        $this->authenticator->expects($this->once())
            ->method('challenge')
            ->with($request, $this->callback(fn (IResponse $response) => $response->getStatusCode() === HttpStatusCode::Unauthorized), 'scheme');
        $this->middleware->setParameters(['policy' => $policy]);
        $response = $this->middleware->handle($request, $this->createMock(IRequestHandler::class));
        $this->assertSame(HttpStatusCode::Unauthorized, $response->getStatusCode());
    }

    /**
     * @dataProvider getInvalidParameters
     *
     * @param string|null $policyName The policy name parameter
     * @param AuthorizationPolicy|null $policy The policy parameter
     * @param string $expectedExceptionMessage The expected exception message
     */
    public function testInvalidParametersThrowsException(
        ?string $policyName,
        ?AuthorizationPolicy $policy,
        string $expectedExceptionMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->middleware->setParameters([
            'policyName' => $policyName,
            'policy' => $policy
        ]);
        $this->middleware->handle($this->createMock(IRequest::class), $this->createMock(IRequestHandler::class));
    }

    /**
     * Creates a mocked authenticated user
     *
     * @return IPrincipal The authenticated mock user
     */
    private function createMockAuthenticatedUser(): IPrincipal
    {
        $identity = $this->createMock(IIdentity::class);
        $identity->method('isAuthenticated')
            ->willReturn(true);
        $user = $this->createMock(IPrincipal::class);
        $user->method('getPrimaryIdentity')
            ->willReturn($identity);

        return $user;
    }
}
