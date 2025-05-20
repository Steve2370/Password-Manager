<?php

namespace Controllers;

use Models\Inventory\Brokers\PasswordBroker;
use Models\Inventory\Entities\PasswordEntry;
use Models\Inventory\Services\EncryptionService;
use Models\Inventory\Services\DatabaseEncrytionService;
use Models\Inventory\Services\UserService;
use Zephyrus\Application\Controller as ZephyrusController;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Put;
use Zephyrus\Network\Router\Delete;

class PasswordController extends ZephyrusController
{
    protected PasswordBroker $passwordBroker;
    protected EncryptionService $encryptionService;
    protected DatabaseEncrytionService $databaseEncryptionService;

    public function before(): ?Response
    {
        if (isset($_SESSION['master_key'])) {
            $this->encryptionService = new EncryptionService($_SESSION['master_key']);
        } else {
            $this->encryptionService = new EncryptionService();
        }

        $this->databaseEncryptionService = new DatabaseEncrytionService($this->encryptionService);
        $this->passwordBroker = new PasswordBroker($this->databaseEncryptionService);

        $url = $_SERVER['REQUEST_URI'];
        $sessionStatus = isset($_SESSION['user_id']) ? "Utilisateur connecté: {$_SESSION['user_id']}" : "Non connecté";
        file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - URL: $url, Status: $sessionStatus\n", FILE_APPEND);

        if (!isset($_SESSION['user_id']) && !in_array($url, ['/login', '/register', '/'])) {
            file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - Redirection vers /login\n", FILE_APPEND);
            return $this->redirect('/login');
        }

        return null;
    }

    #[Get('/')]
    public function home(): Response
    {
        if (isset($_SESSION['user_id'])) {
            return $this->redirect('/dashboard');
        }

        return $this->render('home', [
            'title' => 'Gestionnaire de mots de passe sécurisé'
        ]);
    }

    #[Get('/dashboard')]
    public function dashboard(): Response
    {
        $userService = new UserService();
        $user = $userService->getUserById($_SESSION['user_id']);

        $userId = $_SESSION['user_id'];
        $passwords = $this->passwordBroker->findAllForUser($userId);

        $totalPasswords = count($passwords);

        $strongPasswords = 0;
        $mediumPasswords = 0;
        $weakPasswords = 0;
        $veryWeakPasswords = 0;

        foreach ($passwords as $password) {
            $strength = $this->calculatePasswordStrength($password->servicePassword);

            switch ($strength) {
                case 'strong':
                case 'very_strong':
                    $strongPasswords++;
                    break;
                case 'medium':
                    $mediumPasswords++;
                    break;
                case 'weak':
                    $weakPasswords++;
                    break;
                case 'very_weak':
                    $veryWeakPasswords++;
                    break;
            }
        }

        $recentPasswords = array_slice($passwords, 0, 4);

        return $this->render('dashboard', [
            'title' => 'Tableau de bord',
            'user' => $user,
            'totalPasswords' => $totalPasswords,
            'strongPasswords' => $strongPasswords,
            'weakPasswords' => $weakPasswords + $veryWeakPasswords,
            'warnings' => $weakPasswords + $veryWeakPasswords,
            'recentPasswords' => $recentPasswords,
            'strengthStats' => [
                'strong' => $strongPasswords,
                'very_strong' => 0,
                'medium' => $mediumPasswords,
                'weak' => $weakPasswords,
                'very_weak' => $veryWeakPasswords
            ]
        ]);
    }

    #[Get('/passwords')]
    public function index(): Response
    {
        $userId = $_SESSION['user_id'];
        $passwords = $this->passwordBroker->findAllForUser($userId);
        $categories = $this->passwordBroker->getAllCategories($userId);

        return $this->render('password/index', [
            'title' => 'Mes mots de passe',
            'passwords' => $passwords,
            'categories' => $categories
        ]);
    }

    #[Get('/passwords/shared')]
    public function shared(): Response
    {
        $userId = $_SESSION['user_id'];
        $sharedPasswords = $this->passwordBroker->getSharedPasswords($userId);


        return $this->render('password/shared', [
            'title' => 'Mots de passe partagés avec moi',
            'passwords' => $sharedPasswords
        ]);

    }

    #[Get('/passwords/create')]
    public function create(): Response
    {
        $userId = $_SESSION['user_id'];
        $categories = $this->passwordBroker->getAllCategories($userId);

        return $this->render('password/create', [
            'title' => 'Ajouter un mot de passe',
            'categories' => $categories
        ]);
    }

    #[Post('/passwords')]
    public function store(): Response
    {
        $form = $this->buildForm();

        $serviceName = $form->getValue('service_name');
        $serviceUsername = $form->getValue('service_username');
        $servicePassword = $form->getValue('service_password');

        if (empty($serviceName) || empty($serviceUsername) || empty($servicePassword)) {
            $this->addFlashMessage('danger', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirect('/passwords/create');
        }

        $userId = Session::get('user_id');

        $passwordEntry = PasswordEntry::fromArray([
            'user_id' => $userId,
            'service_name' => $serviceName,
            'service_username' => $serviceUsername,
            'service_password' => $servicePassword,
            'url' => $form->getValue('url', ''),
            'notes' => $form->getValue('notes', ''),
            'category' => $form->getValue('category', '')
        ]);

        $this->passwordBroker->insert($passwordEntry);

        $this->addFlashMessage('success', 'Mot de passe ajouté avec succès.');
        return $this->redirect('/passwords');
    }

    #[Get('/passwords/{id}')]
    public function show(int $id): Response
    {
        $userId = $_SESSION['user_id'];
        $passwordEntry = $this->passwordBroker->findById($id, $userId);

        if ($passwordEntry === null) {
            $this->addFlashMessage('danger', 'Mot de passe introuvable.');
            return $this->redirect('/passwords');
        }

        $history = $this->passwordBroker->getPasswordHistory($id, $userId);

        return $this->render('password/show', [
            'title' => 'Détails du mot de passe',
            'password' => $passwordEntry,
            'history' => $history,
            'strength' => $this->calculatePasswordStrength($passwordEntry->servicePassword)
        ]);
    }

    #[Get('/passwords/{id}/edit')]
    public function edit(int $id): Response
    {
        $userId = $_SESSION['user_id'];
        $passwordEntry = $this->passwordBroker->findById($id, $userId);

        if ($passwordEntry === null) {
            $this->addFlashMessage('danger', 'Mot de passe introuvable.');
            return $this->redirect('/passwords');
        }

        $categories = $this->passwordBroker->getAllCategories($userId);

        $generatedPassword = $_GET['generated_password'] ?? '';
        if (!empty($generatedPassword)) {
            $passwordEntry->servicePassword = $generatedPassword;
        }

        return $this->render('password/edit', [
            'title' => 'Modifier le mot de passe',
            'password' => $passwordEntry,
            'categories' => $categories
        ]);
    }

    #[Put('/passwords/{id}')]
    public function update(int $id): Response
    {
        $userId = $_SESSION['user_id'];
        $passwordEntry = $this->passwordBroker->findById($id, $userId);

        if ($passwordEntry === null) {
            $this->addFlashMessage('danger', 'Mot de passe introuvable.');
            return $this->redirect('/passwords');
        }

        $form = $this->buildForm();

        $serviceName = $form->getValue('service_name');
        $serviceUsername = $form->getValue('service_username');
        $servicePassword = $form->getValue('service_password');

        if (empty($serviceName) || empty($serviceUsername) || empty($servicePassword)) {
            $this->addFlashMessage('danger', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirect("/passwords/$id/edit");
        }

        if ($passwordEntry->servicePassword !== $servicePassword) {
            $oldPasswordData = $passwordEntry->toArray();
            $this->passwordBroker->savePasswordHistory(
                $id,
                $oldPasswordData['service_password'],
                $oldPasswordData['service_password_iv'],
                $userId
            );
        }

        $passwordEntry->serviceName = $serviceName;
        $passwordEntry->serviceUsername = $serviceUsername;
        $passwordEntry->servicePassword = $servicePassword;
        $passwordEntry->url = $form->getValue('url', '');
        $passwordEntry->notes = $form->getValue('notes', '');
        $passwordEntry->category = $form->getValue('category', '');

        $this->passwordBroker->update($passwordEntry);

        $this->addFlashMessage('success', 'Mot de passe mis à jour avec succès.');
        return $this->redirect('/passwords');
    }

    #[Delete('/passwords/{id}')]
    public function destroy(int $id): Response
    {
        $userId = $_SESSION['user_id'];
        $this->passwordBroker->delete($id, $userId);

        $this->addFlashMessage('success', 'Mot de passe supprimé avec succès.');
        return $this->redirect('/passwords');
    }

    #[Get('/passwords/search')]
    public function search(): Response
    {
        $userId = $_SESSION['user_id'];
        $keyword = $_GET['keyword'] ?? '';

        if (empty($keyword)) {
            return $this->redirect('/passwords');
        }

        $passwords = $this->passwordBroker->searchPasswords($userId, $keyword);

        return $this->render('password/search', [
            'title' => 'Résultats de recherche',
            'passwords' => $passwords,
            'keyword' => $keyword
        ]);
    }

    #[Get('/passwords/category/{category}')]
    public function byCategory(string $category): Response
    {
        $userId = $_SESSION['user_id'];
        $passwords = $this->passwordBroker->getPasswordsByCategory($userId, $category);
        $allCategories = $this->passwordBroker->getAllCategories($userId);

        return $this->render('password/category', [
            'title' => "Catégorie: $category",
            'passwords' => $passwords,
            'currentCategory' => $category,
            'categories' => $allCategories
        ]);
    }

    #[Post('/passwords/{id}/share')]
    public function share(int $id): Response
    {
        $userId = $_SESSION['user_id'];
        $form = $this->buildForm();
        $toUsername = $form->getValue('to_username', '');

        if (empty($toUsername)) {
            $this->addFlashMessage('danger', 'Veuillez spécifier un utilisateur avec qui partager.');
            return $this->redirect("/passwords/$id");
        }

        $userService = new UserService();
        $toUser = $userService->getUserByUsername($toUsername);

        if (!$toUser) {
            $this->addFlashMessage('danger', "L'utilisateur '$toUsername' n'existe pas.");
            return $this->redirect("/passwords/$id");
        }

        try {
            $this->passwordBroker->sharePassword($id, $userId, $toUser->id);
            $this->addFlashMessage('success', "Mot de passe partagé avec '$toUsername'.");
            return $this->redirect("/passwords/$id");
        } catch (\Exception $e) {
            $this->addFlashMessage('danger', "Erreur lors du partage: " . $e->getMessage());
            return $this->redirect("/passwords/$id");
        }
    }

    #[Get('/passwords/generate')]
    public function generatePassword(): Response
    {
        $length = (int)($_GET['length'] ?? 16);
        $includeUppercase = ($_GET['uppercase'] ?? '1') === '1';
        $includeLowercase = ($_GET['lowercase'] ?? '1') === '1';
        $includeNumbers = ($_GET['numbers'] ?? '1') === '1';
        $includeSymbols = ($_GET['symbols'] ?? '1') === '1';

        $chars = '';
        if ($includeLowercase) $chars .= 'abcdefghijklmnopqrstuvwxyz';
        if ($includeUppercase) $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($includeNumbers) $chars .= '0123456789';
        if ($includeSymbols) $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';

        if (empty($chars)) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        }

        $password = '';
        $charsLength = strlen($chars);

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }

        return $this->json([
            'password' => $password,
            'strength' => $this->calculatePasswordStrength($password)
        ]);
    }

    private function addFlashMessage(string $type, string $message): void
    {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message
        ];
    }

    private function calculatePasswordStrength(string $password): string
    {
        if (empty($password)) {
            return 'very_weak';
        }

        $score = 0;

        if (strlen($password) > 8) $score++;
        if (strlen($password) > 12) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;

        $score = min(floor($score / 1.5), 4);

        $strengthLevels = [
            'very_weak',
            'weak',
            'medium',
            'strong',
            'very_strong'
        ];

        return $strengthLevels[$score];
    }
}