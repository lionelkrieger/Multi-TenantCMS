<?php

class Registry {
    private static $services = [];

    public static function set($key, $service) {
        self::$services[$key] = $service;
    }

    public static function get($key) {
        return self::$services[$key] ?? null;
    }
}
