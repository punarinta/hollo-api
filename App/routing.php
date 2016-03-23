<?php

Route::add('auth', '/api/auth', \Auth::GUEST, '\App\Controller\Auth');
Route::add('contact', '/api/contact', \Auth::GUEST, '\App\Controller\Contact');
Route::add('message', '/api/message', \Auth::GUEST, '\App\Controller\Message');
Route::add('file', '/api/file', \Auth::GUEST, '\App\Controller\File');
