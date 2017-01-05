<?php

Route::add('auth', '/api/auth', \Auth::GUEST, '\App\Controller\Auth');
Route::add('chat', '/api/chat', \Auth::USER, '\App\Controller\Chat');
Route::add('message', '/api/message', \Auth::USER, '\App\Controller\Message');
Route::add('file', '/api/file', \Auth::USER, '\App\Controller\File');
Route::add('settings', '/api/settings', \Auth::USER, '\App\Controller\Settings');
Route::add('gmail-push', '/api/gmail-push', \Auth::GUEST, '\App\Controller\GmailPush');
Route::add('track', '/api/track', \Auth::GUEST, '\App\Controller\Track');
Route::add('sys', '/api/sys', \Auth::USER, '\App\Controller\Sys');
