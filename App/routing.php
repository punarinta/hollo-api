<?php

Route::add('auth', '/api/auth', \Auth::GUEST, '\App\Controller\Auth');
Route::add('contact', '/api/contact', \Auth::USER, '\App\Controller\Contact');
Route::add('email', '/api/email', \Auth::USER, '\App\Controller\Email');
Route::add('message', '/api/message', \Auth::USER, '\App\Controller\Message');
Route::add('file', '/api/file', \Auth::USER, '\App\Controller\File');
Route::add('profile', '/api/profile', \Auth::USER, '\App\Controller\Profile');
Route::add('context-io', '/api/context-io', \Auth::GUEST, '\App\Controller\ContextIO');
