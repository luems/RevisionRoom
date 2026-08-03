<?php

it('renders the welcome landing page', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
