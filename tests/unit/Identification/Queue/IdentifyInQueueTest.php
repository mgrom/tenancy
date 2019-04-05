<?php declare(strict_types=1);

/*
 * This file is part of the tenancy/tenancy package.
 *
 * (c) Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see http://laravel-tenancy.com
 * @see https://github.com/tenancy
 */

namespace Tenancy\Tests\Identification\Drivers\Queue;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Tenancy\Identification\Contracts\ResolvesTenants;
use Tenancy\Identification\Contracts\Tenant;
use Tenancy\Identification\Drivers\Queue\Providers\IdentificationProvider;
use Tenancy\Testing\TestCase;
use Illuminate\Support\Facades\Bus;
use Tenancy\Environment;

class IdentifyInQueueTest extends TestCase
{
    protected $additionalProviders = [IdentificationProvider::class];

    protected function afterSetUp()
    {
        /** @var ResolvesTenants $resolver */
        $resolver = $this->app->make(ResolvesTenants::class);
        $resolver->addModel(\Tenancy\Testing\Mocks\Tenant::class);
    }

    /**
     * @test
     */
    public function queue_identifies_tenant()
    {
        $tenant = $this->mockTenant();

        $this->environment->setTenant($tenant);

        Event::listen(JobProcessed::class, function ($event) use ($tenant) {
            $payload = json_decode($event->job->getRawBody(), true);

            $this->assertEquals($tenant->getTenantIdentifier(), $payload['tenant_identifier']);
            $this->assertEquals($tenant->getTenantKey(), $payload['tenant_key']);
        });

        dispatch(new Mocks\Job);
    }

    /**
     * @test
     */
    public function override_tenant()
    {
        $tenant = $this->createMockTenant();
        $this->environment->setTenant($tenant);

        $second = $this->createMockTenant();

        Event::listen('mock.tenant.job', function ($event) use ($second) {
            $this->assertEquals($second->getTenantIdentifier(), $event->getTenantIdentifier());
            $this->assertEquals($second->getTenantKey(), $event->getTenantKey());
        });

        dispatch(new Mocks\Job(
            $second->getTenantKey(),
            $second->getTenantIdentifier()
        ));
    }

    /**
     * @test
     */
    public function dispatch_now()
    {
        $tenant = $this->createMockTenant();

        dispatch_now(new Mocks\Job);

        $this->assertNull(
            resolve(Environment::class)->getTenant()
        );
    }

    /**
     * @test
     */
    public function dispatch_now_override()
    {
        $tenant = $this->createMockTenant();

        dispatch_now(new Mocks\Job(
            $tenant->getTenantKey(),
            $tenant->getTenantIdentifier()
        ));

        $environment = resolve(Environment::class);

        $this->assertEquals($tenant->getTenantKey(), $environment->getTenant()->getTenantKey());
        $this->assertEquals($tenant->getTenantIdentifier(), $environment->getTenant()->getTenantIdentifier());
    }
}
