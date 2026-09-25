<?php

test('pages link the web app manifest', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('<meta name="theme-color"', false);
});

test('manifest is valid and its icons exist', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toBe('Wifone')
        ->display->toBe('standalone')
        ->start_url->toBe('/dashboard');

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }

    expect(collect($manifest['icons'])->pluck('sizes'))->toContain('192x192', '512x512');
});

test('service worker precaches files that exist', function () {
    expect(public_path('sw.js'))->toBeFile();

    preg_match('/const PRECACHE = \[(.*?)\];/', file_get_contents(public_path('sw.js')), $matches);
    preg_match_all("/'([^']+)'/", $matches[1] ?? '', $paths);

    expect($paths[1])->not->toBeEmpty()
        ->and(public_path('offline.html'))->toBeFile();

    foreach ($paths[1] as $path) {
        expect(public_path(ltrim($path, '/')))->toBeFile();
    }
});
