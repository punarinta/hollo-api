<?php

Route::add('auth', '/api/auth', \Auth::GUEST, '\App\Controller\Auth');
Route::add('notifications', '/api/notifications', \Auth::USER, '\App\Controller\Notifications');
Route::add('settings', '/api/settings', \Auth::USER, '\App\Controller\Settings');
Route::add('upload-plain', '/api/upload/plain', \Auth::USER, '\App\Controller\Upload', 'plain');
Route::add('upload-encoded', '/api/upload/encoded', \Auth::USER, '\App\Controller\Upload', 'encoded');
Route::add('file', '/api/file', \Auth::USER, '\App\Controller\File');
Route::add('tag', '/api/tag', \Auth::USER, '\App\Controller\Tag');
Route::add('guest', '/api/guest', \Auth::GUEST, '\App\Controller\Guest');
Route::add('user', '/api/user', \Auth::USER, '\App\Controller\User');
Route::add('search', '/api/search', \Auth::USER, '\App\Controller\Search');
Route::add('test', '/api/test', \Auth::GUEST, '\App\Controller\Test');
