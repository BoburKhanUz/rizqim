<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class CustomLogin extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email yoki Telefon (Login)')
            ->placeholder('example@email.com yoki +998901234567')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login = $data['email'];
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        return [
            $fieldType => $login,
            'password' => $data['password'],
        ];
    }
}
