<?php

namespace App\Controllers;

use App\Models\AdminStaffAccountModel;
use App\Models\BuyerAccountModel;
use App\Models\UserModel;

class Auth extends BaseController
{
    public function index()
    {
        if (session()->get('isLoggedIn')) {
            return $this->redirectUser(session()->get('role'));
        }

        return view('auth/login');
    }

    public function register()
    {
        if (session()->get('isLoggedIn')) {
            return $this->redirectUser(session()->get('role'));
        }

        return view('auth/register');
    }

    public function adminRegistration()
    {
        if (session()->get('isLoggedIn')) {
            return $this->redirectUser(session()->get('role'));
        }

        $userModel = new UserModel();

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $role = strtolower(trim((string) $this->request->getPost('role')));

        $userId = $userModel->createUser($username, $password, $role);
        if (!$userId) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $userModel->getCreateUserErrors());
        }

        $adminStaffAccountModel = new AdminStaffAccountModel();
        if (!$adminStaffAccountModel->insert([
            'user_id' => $userId,
            'account_type' => $role,
        ])) {
            $userModel->delete($userId);

            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $adminStaffAccountModel->errors());
        }

        $user = $userModel->find($userId);
        $this->startAuthenticatedSession($user, 'admin_staff');

        return $this->redirectUser($user['role'])->with('success', 'Account created successfully.');
    }

    public function authenticate()
    {
        $session = session();
        $model = new UserModel();

        $identifier = trim((string) $this->request->getVar('username'));
        $password = (string) $this->request->getVar('password');

        $user = $this->findAdminStaffUserByUsername($model, $identifier);

        if (!$user) {
            return redirect()->to('login')->with('msg', 'No admin or staff account matched that username.');
        }

        if (!password_verify($password, $user['password_hash'])) {
            return redirect()->to('login')->with('msg', 'Invalid password');
        }
        $this->startAuthenticatedSession($user, 'admin_staff');

        return $this->redirectUser($user['role']);
    }

    public function storeLogin()
    {
        if (session()->get('isLoggedIn')) {
            return $this->redirectStoreUser(session()->get('role'));
        }

        return view('store/login');
    }

    public function storeRegister()
    {
        if (session()->get('isLoggedIn')) {
            return $this->redirectStoreUser(session()->get('role'));
        }

        return view('store/register', [
            'countries' => $this->getCountryOptions(),
            'phoneCodes' => $this->getPhoneCodeOptions(),
        ]);
    }

    public function storeRegistration()
    {
        if (session()->get('isLoggedIn')) {
            return $this->redirectStoreUser(session()->get('role'));
        }

        $userModel = new UserModel();

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $profile = [
            'first_name' => (string) $this->request->getPost('first_name'),
            'last_name' => (string) $this->request->getPost('last_name'),
            'email' => (string) $this->request->getPost('email'),
            'contact' => trim((string) $this->request->getPost('phone_code')) . trim((string) $this->request->getPost('contact')),
            'date_of_birth' => (string) $this->request->getPost('date_of_birth'),
            'address' => (string) $this->request->getPost('address'),
            'zip_code' => (string) $this->request->getPost('zip_code'),
            'country' => (string) $this->request->getPost('country'),
        ];

        $userId = $userModel->createUser($username, $password, 'user', $profile);
        if (!$userId) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $userModel->getCreateUserErrors());
        }

        $buyerAccountModel = new BuyerAccountModel();
        if (!$buyerAccountModel->insert([
            'user_id' => $userId,
        ])) {
            $userModel->delete($userId);

            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $buyerAccountModel->errors());
        }

        $user = $userModel->find($userId);
        $this->startAuthenticatedSession($user, 'buyer');

        return redirect()->to('user/dashboard')->with('success', 'Buyer account created successfully.');
    }

    public function storeAuthenticate()
    {
        $session = session();
        $model = new UserModel();

        $identifier = trim((string) $this->request->getVar('username'));
        $password = (string) $this->request->getVar('password');

        $user = $this->findBuyerUserByIdentifier($model, $identifier);

        if (!$user) {
            return redirect()->to('buyer/login')->with('msg', 'No buyer account matched that email, contact, or username.');
        }

        if (!password_verify($password, $user['password_hash'])) {
            return redirect()->to('buyer/login')->with('msg', 'Invalid password');
        }
        $this->startAuthenticatedSession($user, 'buyer');

        return redirect()->to('user/dashboard');
    }

    public function logout()
    {
        $session = session();
        $role = (string) $session->get('role');
        $guard = (string) $session->get('auth_guard');

        $redirectTo = match (true) {
            $guard === 'buyer', $role === 'user' => 'buyer/login',
            $role === 'admin', $role === 'staff' => 'login',
            default => 'browse',
        };

        $session->remove(['user_id', 'id', 'username', 'role', 'auth_guard', 'isLoggedIn']);
        $session->destroy();

        return redirect()->to($redirectTo);
    }

    private function startAuthenticatedSession(array $user, string $guard): void
    {
        $session = session();
        $session->remove(['user_id', 'id', 'username', 'role', 'auth_guard', 'isLoggedIn']);
        $session->regenerate(true);
        $session->set([
            'user_id' => (int) $user['id'],
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'auth_guard' => $guard,
            'isLoggedIn' => true,
        ]);
    }

    private function redirectUser(string $role)
    {
        return match ($role) {
            'admin' => redirect()->to('admin/dashboard'),
            'staff' => redirect()->to('staff/dashboard'),
            default => redirect()->to('user/dashboard'),
        };
    }

    private function redirectStoreUser(string $role)
    {
        return match ($role) {
            'user' => redirect()->to('user/dashboard'),
            'admin' => redirect()->to('admin/dashboard'),
            'staff' => redirect()->to('staff/dashboard'),
            default => redirect()->to('/'),
        };
    }

    private function getCountryOptions(): array
    {
        return [
            'Afghanistan',
            'Albania',
            'Algeria',
            'Andorra',
            'Angola',
            'Antigua and Barbuda',
            'Argentina',
            'Armenia',
            'Australia',
            'Austria',
            'Azerbaijan',
            'Bahamas',
            'Bahrain',
            'Bangladesh',
            'Barbados',
            'Belarus',
            'Belgium',
            'Belize',
            'Benin',
            'Bhutan',
            'Bolivia',
            'Bosnia and Herzegovina',
            'Botswana',
            'Brazil',
            'Brunei',
            'Bulgaria',
            'Burkina Faso',
            'Burundi',
            'Cabo Verde',
            'Cambodia',
            'Cameroon',
            'Canada',
            'Central African Republic',
            'Chad',
            'Chile',
            'China',
            'Colombia',
            'Comoros',
            'Congo',
            'Costa Rica',
            'Croatia',
            'Cuba',
            'Cyprus',
            'Czech Republic',
            'Democratic Republic of the Congo',
            'Denmark',
            'Djibouti',
            'Dominica',
            'Dominican Republic',
            'Ecuador',
            'Egypt',
            'El Salvador',
            'Equatorial Guinea',
            'Eritrea',
            'Estonia',
            'Eswatini',
            'Ethiopia',
            'Fiji',
            'Finland',
            'France',
            'Gabon',
            'Gambia',
            'Georgia',
            'Germany',
            'Ghana',
            'Greece',
            'Grenada',
            'Guatemala',
            'Guinea',
            'Guinea-Bissau',
            'Guyana',
            'Haiti',
            'Honduras',
            'Hungary',
            'Iceland',
            'India',
            'Indonesia',
            'Iran',
            'Iraq',
            'Ireland',
            'Israel',
            'Italy',
            'Jamaica',
            'Japan',
            'Jordan',
            'Kazakhstan',
            'Kenya',
            'Kiribati',
            'Kuwait',
            'Kyrgyzstan',
            'Laos',
            'Latvia',
            'Lebanon',
            'Lesotho',
            'Liberia',
            'Libya',
            'Liechtenstein',
            'Lithuania',
            'Luxembourg',
            'Madagascar',
            'Malawi',
            'Malaysia',
            'Maldives',
            'Mali',
            'Malta',
            'Marshall Islands',
            'Mauritania',
            'Mauritius',
            'Mexico',
            'Micronesia',
            'Moldova',
            'Monaco',
            'Mongolia',
            'Montenegro',
            'Morocco',
            'Mozambique',
            'Myanmar',
            'Namibia',
            'Nauru',
            'Nepal',
            'Netherlands',
            'New Zealand',
            'Nicaragua',
            'Niger',
            'Nigeria',
            'North Korea',
            'North Macedonia',
            'Norway',
            'Oman',
            'Pakistan',
            'Palau',
            'Panama',
            'Papua New Guinea',
            'Paraguay',
            'Peru',
            'Philippines',
            'Poland',
            'Portugal',
            'Qatar',
            'Romania',
            'Russia',
            'Rwanda',
            'Saint Kitts and Nevis',
            'Saint Lucia',
            'Saint Vincent and the Grenadines',
            'Samoa',
            'San Marino',
            'Sao Tome and Principe',
            'Saudi Arabia',
            'Senegal',
            'Serbia',
            'Seychelles',
            'Sierra Leone',
            'Singapore',
            'Slovakia',
            'Slovenia',
            'Solomon Islands',
            'Somalia',
            'South Africa',
            'South Korea',
            'South Sudan',
            'Spain',
            'Sri Lanka',
            'Sudan',
            'Suriname',
            'Sweden',
            'Switzerland',
            'Syria',
            'Taiwan',
            'Tajikistan',
            'Tanzania',
            'Thailand',
            'Timor-Leste',
            'Togo',
            'Tonga',
            'Trinidad and Tobago',
            'Tunisia',
            'Turkey',
            'Turkmenistan',
            'Tuvalu',
            'Uganda',
            'Ukraine',
            'United Arab Emirates',
            'United Kingdom',
            'United States',
            'Uruguay',
            'Uzbekistan',
            'Vanuatu',
            'Vatican City',
            'Venezuela',
            'Vietnam',
            'Yemen',
            'Zambia',
            'Zimbabwe',
        ];
    }

    private function findAdminStaffUserByUsername(UserModel $model, string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        return $model
            ->select('users.*')
            ->join('admin_staff_accounts', 'admin_staff_accounts.user_id = users.id')
            ->where('users.username', $identifier)
            ->whereIn('users.role', ['admin', 'staff'])
            ->first();
    }

    private function findBuyerUserByIdentifier(UserModel $model, string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $builder = $model
            ->select('users.*')
            ->join('buyer_accounts', 'buyer_accounts.user_id = users.id')
            ->groupStart()
                ->where('username', $identifier)
                ->orWhere('email', $identifier)
                ->orWhere('contact', $identifier)
            ->groupEnd()
            ->where('role', 'user');

        $user = $builder->first();
        if ($user) {
            return $user;
        }

        $digitsOnly = preg_replace('/\D+/', '', $identifier);
        if ($digitsOnly === '') {
            return null;
        }

        $contactsToTry = array_values(array_unique([
            $digitsOnly,
            '+' . $digitsOnly,
            ltrim($identifier, '+'),
            '+' . ltrim($identifier, '+'),
        ]));

        foreach ($contactsToTry as $contactValue) {
            $builder = $model
                ->select('users.*')
                ->join('buyer_accounts', 'buyer_accounts.user_id = users.id')
                ->where('contact', $contactValue)
                ->where('role', 'user');

            $user = $builder->first();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function getPhoneCodeOptions(): array
    {
        return [
            '+93' => 'Afghanistan (+93)',
            '+355' => 'Albania (+355)',
            '+213' => 'Algeria (+213)',
            '+376' => 'Andorra (+376)',
            '+244' => 'Angola (+244)',
            '+54' => 'Argentina (+54)',
            '+374' => 'Armenia (+374)',
            '+61' => 'Australia (+61)',
            '+43' => 'Austria (+43)',
            '+994' => 'Azerbaijan (+994)',
            '+973' => 'Bahrain (+973)',
            '+880' => 'Bangladesh (+880)',
            '+375' => 'Belarus (+375)',
            '+32' => 'Belgium (+32)',
            '+501' => 'Belize (+501)',
            '+229' => 'Benin (+229)',
            '+975' => 'Bhutan (+975)',
            '+591' => 'Bolivia (+591)',
            '+387' => 'Bosnia and Herzegovina (+387)',
            '+267' => 'Botswana (+267)',
            '+55' => 'Brazil (+55)',
            '+673' => 'Brunei (+673)',
            '+359' => 'Bulgaria (+359)',
            '+226' => 'Burkina Faso (+226)',
            '+257' => 'Burundi (+257)',
            '+855' => 'Cambodia (+855)',
            '+237' => 'Cameroon (+237)',
            '+1' => 'Canada/US (+1)',
            '+238' => 'Cabo Verde (+238)',
            '+236' => 'Central African Republic (+236)',
            '+235' => 'Chad (+235)',
            '+56' => 'Chile (+56)',
            '+86' => 'China (+86)',
            '+57' => 'Colombia (+57)',
            '+269' => 'Comoros (+269)',
            '+242' => 'Congo (+242)',
            '+506' => 'Costa Rica (+506)',
            '+385' => 'Croatia (+385)',
            '+53' => 'Cuba (+53)',
            '+357' => 'Cyprus (+357)',
            '+420' => 'Czech Republic (+420)',
            '+243' => 'DR Congo (+243)',
            '+45' => 'Denmark (+45)',
            '+253' => 'Djibouti (+253)',
            '+20' => 'Egypt (+20)',
            '+503' => 'El Salvador (+503)',
            '+372' => 'Estonia (+372)',
            '+251' => 'Ethiopia (+251)',
            '+679' => 'Fiji (+679)',
            '+358' => 'Finland (+358)',
            '+33' => 'France (+33)',
            '+995' => 'Georgia (+995)',
            '+49' => 'Germany (+49)',
            '+233' => 'Ghana (+233)',
            '+30' => 'Greece (+30)',
            '+502' => 'Guatemala (+502)',
            '+224' => 'Guinea (+224)',
            '+592' => 'Guyana (+592)',
            '+509' => 'Haiti (+509)',
            '+504' => 'Honduras (+504)',
            '+36' => 'Hungary (+36)',
            '+354' => 'Iceland (+354)',
            '+91' => 'India (+91)',
            '+62' => 'Indonesia (+62)',
            '+98' => 'Iran (+98)',
            '+964' => 'Iraq (+964)',
            '+353' => 'Ireland (+353)',
            '+972' => 'Israel (+972)',
            '+39' => 'Italy (+39)',
            '+81' => 'Japan (+81)',
            '+962' => 'Jordan (+962)',
            '+7' => 'Kazakhstan/Russia (+7)',
            '+254' => 'Kenya (+254)',
            '+965' => 'Kuwait (+965)',
            '+996' => 'Kyrgyzstan (+996)',
            '+856' => 'Laos (+856)',
            '+371' => 'Latvia (+371)',
            '+961' => 'Lebanon (+961)',
            '+231' => 'Liberia (+231)',
            '+218' => 'Libya (+218)',
            '+423' => 'Liechtenstein (+423)',
            '+370' => 'Lithuania (+370)',
            '+352' => 'Luxembourg (+352)',
            '+261' => 'Madagascar (+261)',
            '+60' => 'Malaysia (+60)',
            '+960' => 'Maldives (+960)',
            '+356' => 'Malta (+356)',
            '+230' => 'Mauritius (+230)',
            '+52' => 'Mexico (+52)',
            '+373' => 'Moldova (+373)',
            '+377' => 'Monaco (+377)',
            '+976' => 'Mongolia (+976)',
            '+382' => 'Montenegro (+382)',
            '+212' => 'Morocco (+212)',
            '+258' => 'Mozambique (+258)',
            '+95' => 'Myanmar (+95)',
            '+264' => 'Namibia (+264)',
            '+977' => 'Nepal (+977)',
            '+31' => 'Netherlands (+31)',
            '+64' => 'New Zealand (+64)',
            '+505' => 'Nicaragua (+505)',
            '+234' => 'Nigeria (+234)',
            '+47' => 'Norway (+47)',
            '+968' => 'Oman (+968)',
            '+92' => 'Pakistan (+92)',
            '+507' => 'Panama (+507)',
            '+675' => 'Papua New Guinea (+675)',
            '+595' => 'Paraguay (+595)',
            '+51' => 'Peru (+51)',
            '+63' => 'Philippines (+63)',
            '+48' => 'Poland (+48)',
            '+351' => 'Portugal (+351)',
            '+974' => 'Qatar (+974)',
            '+40' => 'Romania (+40)',
            '+250' => 'Rwanda (+250)',
            '+966' => 'Saudi Arabia (+966)',
            '+221' => 'Senegal (+221)',
            '+381' => 'Serbia (+381)',
            '+65' => 'Singapore (+65)',
            '+421' => 'Slovakia (+421)',
            '+386' => 'Slovenia (+386)',
            '+252' => 'Somalia (+252)',
            '+27' => 'South Africa (+27)',
            '+82' => 'South Korea (+82)',
            '+211' => 'South Sudan (+211)',
            '+34' => 'Spain (+34)',
            '+94' => 'Sri Lanka (+94)',
            '+249' => 'Sudan (+249)',
            '+597' => 'Suriname (+597)',
            '+46' => 'Sweden (+46)',
            '+41' => 'Switzerland (+41)',
            '+963' => 'Syria (+963)',
            '+886' => 'Taiwan (+886)',
            '+992' => 'Tajikistan (+992)',
            '+255' => 'Tanzania (+255)',
            '+66' => 'Thailand (+66)',
            '+216' => 'Tunisia (+216)',
            '+90' => 'Turkey (+90)',
            '+993' => 'Turkmenistan (+993)',
            '+256' => 'Uganda (+256)',
            '+380' => 'Ukraine (+380)',
            '+971' => 'United Arab Emirates (+971)',
            '+44' => 'United Kingdom (+44)',
            '+598' => 'Uruguay (+598)',
            '+998' => 'Uzbekistan (+998)',
            '+58' => 'Venezuela (+58)',
            '+84' => 'Vietnam (+84)',
            '+967' => 'Yemen (+967)',
            '+260' => 'Zambia (+260)',
            '+263' => 'Zimbabwe (+263)',
        ];
    }
}



