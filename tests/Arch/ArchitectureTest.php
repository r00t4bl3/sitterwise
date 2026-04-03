<?php

describe('Architecture Tests', function () {
    test('models extend base model', function () {
        $this->expect('App\Models')
            ->classes()
            ->toExtend('Illuminate\Database\Eloquent\Model');
    });

    test('controllers extend base controller', function () {
        $this->expect('App\Http\Controllers')
            ->classes()
            ->toExtend('App\Http\Controllers\Controller');
    });

    test('form requests extend base form request', function () {
        $this->expect('App\Http\Requests')
            ->classes()
            ->toExtend('Illuminate\Foundation\Http\FormRequest');
    });

    test('middleware has handle method', function () {
        $this->expect('App\Http\Middleware')
            ->classes()
            ->toHaveMethod('handle');
    });

    test('factories extend base factory', function () {
        $this->expect('Database\Factories')
            ->classes()
            ->toExtend('Illuminate\Database\Eloquent\Factories\Factory');
    });

    test('seeders extend base seeder', function () {
        $this->expect('Database\Seeders')
            ->classes()
            ->toExtend('Illuminate\Database\Seeder');
    });

    test('enums are backed enums', function () {
        $this->expect('App\Enums')
            ->classes()
            ->toBeEnums();
    });

    test('policies define policy methods', function () {
        $this->expect('App\Policies')
            ->classes()
            ->toHaveMethod('viewAny');
    });

    test('no direct database calls in controllers', function () {
        $this->expect('App\Http\Controllers')
            ->not->toUse('DB');
    });

    test('no direct database calls in views', function () {
        $this->expect('App\Http\Controllers')
            ->not->toUse('DB::');
    });

    test('env is only used in config files', function () {
        $this->expect('App')
            ->not->toUse('env');
    });

    test('tests extend test case', function () {
        $this->expect('Tests')
            ->classes()
            ->toExtend('Tests\TestCase')
            ->ignoring('Tests\TestCase');
    });
});
