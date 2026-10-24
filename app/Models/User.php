<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'color'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['abilities'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Whether the editor's Review button starts a review for this user. With the API any admin can; with Claude
     * Code only the owner of the Claude plan it runs on (config ai-review.claude_code.user).
     */
    public function canStartAiReviews(): bool
    {
        if (! $this->isAdmin() || ! config('ai-review.enabled')) {
            return false;
        }
        if (config('ai-review.runner') === 'api') {
            return true;
        }
        $owner = config('ai-review.claude_code.user');

        return is_string($owner) && $owner !== '' && strcasecmp($owner, $this->email) === 0;
    }

    /**
     * What the SPA should offer this user, sent with the user (login, /api/user).
     */
    protected function abilities(): Attribute
    {
        return Attribute::get(fn () => ['aiReview' => $this->isAdmin() && config('ai-review.enabled')]);
    }
}
