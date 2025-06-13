<?php

namespace ElliottLawson\ConversePrism\Tests\Models;

use ElliottLawson\ConversePrism\Concerns\HasAIConversations;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    use HasAIConversations;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}