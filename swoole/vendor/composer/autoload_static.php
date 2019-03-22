<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc5b72bf0bb9313d49c30468c2bbdbc68
{
    public static $prefixLengthsPsr4 = array (
        'H' => 
        array (
            'HomeWork\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'HomeWork\\' => 
        array (
            0 => __DIR__ . '/../..' . '/homeWork',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc5b72bf0bb9313d49c30468c2bbdbc68::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc5b72bf0bb9313d49c30468c2bbdbc68::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}