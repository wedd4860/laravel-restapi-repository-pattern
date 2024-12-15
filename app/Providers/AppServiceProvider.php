<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 공통 패턴 추가
        Validator::extend('specialChar1', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[\pL\d_\-#]+$/u', $value); // 모든 언어, 숫자, 대시, 밑줄, 해시만 허용
        });

        // specialChar1 + 공백 추가
        Validator::extend('specialChar2', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[\pL\d_\-\s#]+$/u', $value); // 모든 언어, 숫자, 대시, 밑줄, 해시, 공백 허용
        });
    }
}
