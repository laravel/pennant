<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Attributes\Name;
use Laravel\Pennant\Attributes\Store;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    public function test_active_and_inactive_accept_enums()
    {
        Feature::define(FeatureEnum::NewApi, fn () => true);

        $this->assertTrue(Feature::active(FeatureEnum::NewApi));
        $this->assertFalse(Feature::inactive(FeatureEnum::NewApi));
    }

    public function test_an_enum_and_its_backing_value_reference_the_same_feature()
    {
        Feature::activate(FeatureEnum::NewApi, 'foo');

        $this->assertSame('foo', Feature::value(FeatureEnum::NewApi));
        $this->assertSame('foo', Feature::value('new-api'));
        $this->assertTrue(Feature::active('new-api'));
    }

    public function test_a_pure_enum_resolves_to_its_case_name()
    {
        Feature::activate(PureFeatureEnum::NewApi);

        $this->assertTrue(Feature::active(PureFeatureEnum::NewApi));
        $this->assertTrue(Feature::active('NewApi'));
    }

    public function test_an_integer_backed_enum_resolves_to_its_string_backing_value()
    {
        Feature::activate(IntegerFeatureEnum::NewApi, 'foo');

        $this->assertSame('foo', Feature::value(IntegerFeatureEnum::NewApi));
        $this->assertSame('foo', Feature::value('1'));
        $this->assertSame(['1' => 'foo'], Feature::values([IntegerFeatureEnum::NewApi]));
        $this->assertSame(['1' => ['foo']], Feature::load([IntegerFeatureEnum::NewApi]));
    }

    public function test_define_accepts_an_enum_with_a_resolver()
    {
        Feature::define(FeatureEnum::NewApi, fn () => 'resolved-value');

        $this->assertSame('resolved-value', Feature::value(FeatureEnum::NewApi));
        $this->assertSame('resolved-value', Feature::value('new-api'));
    }

    public function test_value_and_values_accept_enums()
    {
        Feature::define(FeatureEnum::NewApi, fn () => true);
        Feature::define(FeatureEnum::Purchasing, fn () => false);

        $this->assertSame([
            'new-api' => true,
            'purchasing' => false,
        ], Feature::values([FeatureEnum::NewApi, FeatureEnum::Purchasing]));
    }

    public function test_all_are_active_and_some_are_active_accept_enums()
    {
        Feature::activate(FeatureEnum::NewApi);
        Feature::activate(FeatureEnum::Purchasing);

        $this->assertTrue(Feature::allAreActive([FeatureEnum::NewApi, FeatureEnum::Purchasing]));
        $this->assertTrue(Feature::someAreActive([FeatureEnum::NewApi, FeatureEnum::Purchasing]));

        // Enums and their string values may be freely mixed in the same call.
        $this->assertTrue(Feature::allAreActive([FeatureEnum::NewApi, 'purchasing']));

        Feature::deactivate(FeatureEnum::Purchasing);

        $this->assertFalse(Feature::allAreActive([FeatureEnum::NewApi, FeatureEnum::Purchasing]));
        $this->assertTrue(Feature::someAreActive([FeatureEnum::NewApi, FeatureEnum::Purchasing]));
    }

    public function test_all_are_inactive_and_some_are_inactive_accept_enums()
    {
        Feature::activate(FeatureEnum::NewApi);
        Feature::deactivate(FeatureEnum::Purchasing);

        $this->assertTrue(Feature::allAreInactive([FeatureEnum::Purchasing]));
        $this->assertFalse(Feature::allAreInactive([FeatureEnum::NewApi, FeatureEnum::Purchasing]));
        $this->assertTrue(Feature::someAreInactive([FeatureEnum::NewApi, FeatureEnum::Purchasing]));
    }

    public function test_when_and_unless_accept_enums()
    {
        Feature::activate(FeatureEnum::NewApi, 'foo');

        $this->assertSame('active-foo', Feature::when(
            FeatureEnum::NewApi,
            fn ($value) => "active-{$value}",
            fn () => 'inactive',
        ));

        $this->assertSame('inactive', Feature::unless(
            FeatureEnum::Purchasing,
            fn () => 'inactive',
            fn () => 'active',
        ));
    }

    public function test_activate_deactivate_and_forget_accept_enums()
    {
        Feature::activate(FeatureEnum::NewApi);
        $this->assertTrue(Feature::active(FeatureEnum::NewApi));

        Feature::deactivate(FeatureEnum::NewApi);
        $this->assertFalse(Feature::active(FeatureEnum::NewApi));

        Feature::define(FeatureEnum::NewApi, fn () => true);
        Feature::forget(FeatureEnum::NewApi);
        $this->assertTrue(Feature::active(FeatureEnum::NewApi));
    }

    public function test_load_and_load_missing_accept_enums()
    {
        Feature::define(FeatureEnum::NewApi, fn () => true);
        Feature::define(FeatureEnum::Purchasing, fn () => false);

        $this->assertSame([
            'new-api' => [true],
            'purchasing' => [false],
        ], Feature::loadMissing([FeatureEnum::NewApi, FeatureEnum::Purchasing]));

        $this->assertSame([
            'new-api' => [true],
        ], Feature::load([FeatureEnum::NewApi]));
    }

    public function test_activate_for_everyone_and_purge_accept_enums()
    {
        Feature::define(FeatureEnum::NewApi, fn () => false);

        // Resolve the scope so the array driver tracks it for "all scopes" updates.
        Feature::for('tim')->active(FeatureEnum::NewApi);

        Feature::activateForEveryone(FeatureEnum::NewApi);
        $this->assertTrue(Feature::for('tim')->active(FeatureEnum::NewApi));

        Feature::deactivateForEveryone(FeatureEnum::NewApi);
        $this->assertFalse(Feature::for('tim')->active(FeatureEnum::NewApi));

        Feature::activate(FeatureEnum::NewApi);
        Feature::purge(FeatureEnum::NewApi);
        $this->assertFalse(Feature::active(FeatureEnum::NewApi));
    }

    public function test_scoped_interactions_accept_enums()
    {
        Feature::for('tim')->activate(FeatureEnum::NewApi);

        $this->assertTrue(Feature::for('tim')->active(FeatureEnum::NewApi));
        $this->assertTrue(Feature::for('tim')->active('new-api'));
        $this->assertFalse(Feature::for('jess')->active(FeatureEnum::NewApi));
    }

    public function test_instance_accepts_an_enum()
    {
        Feature::define(FeatureEnum::NewApi, $resolver = fn () => 'resolved');

        $this->assertSame($resolver, Feature::instance(FeatureEnum::NewApi));
        $this->assertSame($resolver, Feature::instance('new-api'));
    }

    public function test_the_name_attribute_accepts_an_enum()
    {
        $this->assertSame('purchasing', Feature::name(FeatureWithEnumName::class));

        $this->assertTrue(Feature::active(FeatureWithEnumName::class));
        $this->assertTrue(Feature::active(FeatureEnum::Purchasing));
        $this->assertTrue(Feature::active('purchasing'));
    }

    public function test_the_store_attribute_accepts_an_enum()
    {
        Config::set('pennant.stores.secondary', ['driver' => 'array']);

        $this->assertTrue(Feature::active(FeatureWithEnumStore::class));

        $this->assertSame(['enum-store'], Feature::store('secondary')->stored());
        $this->assertSame([], Feature::store('array')->stored());
    }

    public function test_the_blade_directive_accepts_an_enum()
    {
        $blade = <<<'BLADE'
            @feature(\Tests\Feature\FeatureEnum::NewApi)
                new api is active
            @else
                new api is inactive
            @endfeature
            BLADE;

        $this->assertSame('new api is inactive', trim(Blade::render($blade)));

        Feature::activate(FeatureEnum::NewApi);

        $this->assertSame('new api is active', trim(Blade::render($blade)));
    }

    public function test_the_middleware_using_method_accepts_enums()
    {
        $this->assertSame(
            EnsureFeaturesAreActive::class.':new-api,purchasing',
            EnsureFeaturesAreActive::using(FeatureEnum::NewApi, FeatureEnum::Purchasing),
        );
    }

    public function test_the_middleware_handle_method_accepts_enums()
    {
        Feature::define(FeatureEnum::NewApi, fn () => true);

        $response = (new EnsureFeaturesAreActive)->handle(
            $this->createRequest('test', 'get'),
            fn () => new Response,
            FeatureEnum::NewApi,
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->expectException(HttpException::class);

        (new EnsureFeaturesAreActive)->handle(
            $this->createRequest('test', 'get'),
            fn () => new Response,
            FeatureEnum::Purchasing,
        );
    }

    protected function createRequest(string $uri, string $method): Request
    {
        return Request::createFromBase(SymfonyRequest::create(uri: $uri, method: $method));
    }
}

enum FeatureEnum: string
{
    case NewApi = 'new-api';
    case Purchasing = 'purchasing';
}

enum IntegerFeatureEnum: int
{
    case NewApi = 1;
    case Purchasing = 2;
}

enum PureFeatureEnum
{
    case NewApi;
    case Purchasing;
}

#[Name(FeatureEnum::Purchasing)]
class FeatureWithEnumName
{
    public function resolve(): bool
    {
        return true;
    }
}

enum StoreEnum: string
{
    case Secondary = 'secondary';
}

#[Name('enum-store')]
#[Store(StoreEnum::Secondary)]
class FeatureWithEnumStore
{
    public function resolve(): bool
    {
        return true;
    }
}
