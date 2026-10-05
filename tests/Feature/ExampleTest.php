<?php

test('the root page redirects to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
