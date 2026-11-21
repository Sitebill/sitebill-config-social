<?php

namespace SitebillApps\Rumantic\Social\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Только чтение: автор демо-постов. Таблица пользователей общая, своих колонок приложение не добавляет. */
class User extends Authenticatable
{
    protected $table = 'users';
}
