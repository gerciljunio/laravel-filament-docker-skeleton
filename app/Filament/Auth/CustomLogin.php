<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Filament\Schemas\Components\Form;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Notifications\Notification;

class CustomLogin extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        $code = request()->get('code');
        $email = request()->get('email');
        $showCode = request()->boolean('showCode');

        return $schema->schema([
            TextInput::make('email')
                ->label(__('filament-panels::auth/pages/login.form.email.label'))
                ->email()
                ->required()
                ->autocomplete()
                ->autofocus()
                ->extraInputAttributes(['tabindex' => 1])
                ->default(fn () => $email),

            TextInput::make('password')
                ->label(__('filament-panels::auth/pages/login.form.password.label'))
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->autocomplete('current-password')
                // senha só é obrigatória quando showCode=1 ou quando existe ?code=
                ->required(fn () => request()->boolean('showCode') || filled(request('code')))
                ->extraInputAttributes(['tabindex' => 2])
                ->visible(fn () => filled($code) || request()->method() === 'POST' || request()->get('showCode'))
                ->default(fn () => $code),

            Checkbox::make('remember')
                ->label(__('filament-panels::auth/pages/login.form.remember.label')),
        ])->statePath('data');
    }

    /**
     * Ponto-chave: garanta 'password' no array mesmo quando o campo estiver oculto.
     * Mantém o authenticate() padrão do Filament funcionando.
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        // Se veio ?code e o campo não foi renderizado, injeta a chave:
        if (! array_key_exists('password', $data)) {
            $data['password'] = request()->get('code') ?? '';
        }

        // Retorna no formato esperado pelo guard:
        return [
            'email'    => $data['email'] ?? '',
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        // pré-validações/opcional
        $data = $this->form->getState();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$password) {

            // Processo de login com senha temporária: cria, envia, valida, usa, altera
            //

            // Mensagem de confirmação
            Notification::make()
                ->title('E-mail enviado!')
                ->body('Enviamos sua senha temporária para ' . $email . '!')
                ->success()
                ->duration(8000)
                ->send();

            $this->redirect('/admin/login?showCode=1&email=' . urlencode((string) $email), navigate: true);
            return null;
        }

        // logger('cheguei ao fim, login');
        return parent::authenticate();
    }
}
