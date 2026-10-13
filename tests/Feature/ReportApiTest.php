<?php

test('the API only application has no root web page', function () {
    $response = $this->get('/');

    $response->assertNotFound();
});
