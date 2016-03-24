<?php

Route::add('auth', '/api/auth', \Auth::GUEST, '\App\Controller\Auth');
Route::add('contact', '/api/contact', \Auth::USER, '\App\Controller\Contact');
Route::add('message', '/api/message', \Auth::USER, '\App\Controller\Message');
Route::add('file', '/api/file', \Auth::USER, '\App\Controller\File');
