<?php

namespace Alif\LaravelRabbitmq\Tests;

use Alif\LaravelRabbitmq\BaseDto;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BaseDtoTest extends TestCase
{
    public function test_fromArray_hydrates_when_no_rules_are_defined(): void
    {
        $dto = DtoWithoutRules::fromArray(['id' => '5']);

        $this->assertSame('5', $dto->id);
    }

    public function test_fromArray_hydrates_when_data_passes_rules(): void
    {
        $dto = DtoWithRules::fromArray(['email' => 'a@b.com', 'name' => 'Ada']);

        $this->assertSame('a@b.com', $dto->email);
        $this->assertSame('Ada', $dto->name);
    }

    public function test_fromArray_throws_validation_exception_when_data_fails_rules(): void
    {
        $this->expectException(ValidationException::class);

        DtoWithRules::fromArray(['email' => 'not-an-email', 'name' => '']);
    }

    public function test_fromArray_still_throws_for_missing_required_field_after_validation_passes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // "name" is optional under the rules (not marked required), so validation
        // passes, but the constructor still requires it with no default.
        DtoWithOptionalRule::fromArray(['name' => 'Ada']);
    }
}

class DtoWithoutRules extends BaseDto
{
    public function __construct(public string $id)
    {
    }
}

class DtoWithRules extends BaseDto
{
    public function __construct(public string $email, public string $name)
    {
    }

    public static function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name' => ['required', 'string'],
        ];
    }
}

class DtoWithOptionalRule extends BaseDto
{
    public function __construct(public string $name, public string $email)
    {
    }

    public static function rules(): array
    {
        return [
            'email' => ['sometimes', 'email'],
        ];
    }
}
