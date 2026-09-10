<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Account;
use App\Enums\TeamType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $accountType = Account::tryFrom($input['account_type'] ?? '') ?? Account::Company;

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'account_type' => ['required', Rule::enum(Account::class)],
            'company_name' => [
                Rule::requiredIf($accountType === Account::Company),
                'nullable', 'string', 'max:255',
            ],
            'ruc' => [
                Rule::requiredIf($accountType === Account::Company),
                'nullable', 'string', 'size:13', 'regex:/^[0-9]+$/',
                Rule::unique('companies', 'ruc'),
            ],
        ])->validate();

        return DB::transaction(function () use ($input, $accountType) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'account_type' => $accountType,
            ]);

            if ($accountType === Account::Company) {
                // El team ES la empresa: no es "personal", es el tenant real de trabajo.
                $this->createTeam->handle(
                    user: $user,
                    name: $input['company_name'],
                    isPersonal: false,
                    type: TeamType::Company,
                    companyData: [
                        'ruc' => $input['ruc'],
                        'business_name' => $input['company_name'],
                    ],
                );
            } else {
                // Contador: team de trabajo personal, sin Company propia.
                // Sus clientes los crea después, cada uno como su propio team + company.
                $this->createTeam->handle(
                    user: $user,
                    name: $user->name."'s Team",
                    isPersonal: true,
                    type: TeamType::Accountant,
                );
            }

            return $user;
        });
    }
}