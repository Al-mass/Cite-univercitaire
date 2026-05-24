<?php
require_once '../includes/functions.php';

// Si l'utilisateur est déjà connecté, le rediriger
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('../admin/dashboard.php');
    } else {
        redirect('../etudiant/dashboard.php');
    }
}

$error = '';
$success = '';

// Vérifier s'il y a un message de succès (après inscription)
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    // Validation de base
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format d\'email invalide';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Récupérer l'utilisateur
            $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Vérifier si le compte est actif
                if (!$user['statut']) {
                    $error = 'Votre compte a été désactivé. Veuillez contacter l\'administration.';
                }
                // Vérifier le mot de passe
                elseif (password_verify($password, $user['mot_de_passe'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Gestion du "Se souvenir de moi"
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        // Stocker le token dans la base de données
                        $stmt = $db->prepare("UPDATE utilisateurs SET remember_token = ?, token_expires = ? WHERE id = ?");
                        $stmt->execute([$token, $expires, $user['id']]);
                        
                        // Créer le cookie
                        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
                    }
                    
                    // Logger la connexion
                    logAction('connexion', $user['id'], 'Connexion réussie');
                    
                    // Envoyer une notification de connexion
                    $message = "Nouvelle connexion à votre compte le " . date('d/m/Y à H:i');
                    envoyerNotification($user['id'], 'systeme', $message);
                    
                    // Redirection selon le rôle
                    if ($user['role'] == 'admin') {
                        redirect('../admin/dashboard.php');
                    } else {
                        redirect('../etudiant/dashboard.php');
                    }
                } else {
                    $error = 'Email ou mot de passe incorrect';
                    logAction('tentative_connexion_echouee', null, "Email: $email - Mot de passe incorrect");
                }
            } else {
                $error = 'Email ou mot de passe incorrect';
                logAction('tentative_connexion_echouee', null, "Email: $email - Utilisateur non trouvé");
            }
        } catch (PDOException $e) {
            $error = 'Erreur de connexion à la base de données. Veuillez réessayer.';
            error_log('Erreur login: ' . $e->getMessage());
        }
    }
}

// Vérifier le cookie "Se souvenir de moi"
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE remember_token = ? AND token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        logAction('connexion_auto', $user['id'], 'Connexion automatique via cookie');
        
        if ($user['role'] == 'admin') {
            redirect('../admin/dashboard.php');
        } else {
            redirect('../etudiant/dashboard.php');
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4">
                <i class="bi bi-building display-1 text-primary"></i>
                <h2 class="mt-2">Connexion</h2>
                <p class="text-muted">Accédez à votre espace personnel</p>
            </div>
            
            <div class="card shadow">
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="loginForm" class="needs-validation" novalidate>
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope"></i> Adresse email
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-at"></i></span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="exemple@email.com" 
                                       required
                                       autocomplete="email"
                                       autofocus>
                                <div class="invalid-feedback">
                                    Veuillez entrer une adresse email valide
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mot de passe -->
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock"></i> Mot de passe
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="••••••••" 
                                       required
                                       autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Veuillez entrer votre mot de passe
                                </div>
                            </div>
                        </div>
                        
                        <!-- Se souvenir de moi -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Se souvenir de moi
                            </label>
                        </div>
                        
                        <!-- Bouton de connexion -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="bi bi-box-arrow-in-right"></i> Se connecter
                                <span class="spinner-border spinner-border-sm d-none" id="loginSpinner" role="status"></span>
                            </button>
                        </div>
                        
                        <!-- Lien mot de passe oublié -->
                        <div class="text-center mt-3">
                            <a href="mot-de-passe-oublie.php" class="text-decoration-none">
                                <i class="bi bi-question-circle"></i> Mot de passe oublié ?
                            </a>
                        </div>
                    </form>
                    
                    <!-- Séparateur -->
                    <div class="text-center my-4">
                        <span class="px-3 text-muted">ou</span>
                        <hr class="my-0">
                    </div>
                    
                    <!-- Lien vers inscription -->
                    <div class="text-center">
                        <p class="mb-0">Pas encore de compte ?</p>
                        <a href="register.php" class="btn btn-outline-primary mt-2">
                            <i class="bi bi-person-plus"></i> Créer un compte
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Retour à l'accueil -->
            <div class="text-center mt-4">
                <a href="../index.php" class="text-muted text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Afficher le spinner
                document.getElementById('loginBtn').disabled = true;
                document.getElementById('loginSpinner').classList.remove('d-none');
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});

// Empêcher la soumission multiple du formulaire
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    if (this.checkValidity()) {
        btn.disabled = true;
        document.getElementById('loginSpinner').classList.remove('d-none');
    }
});

// Si l'utilisateur revient en arrière, réactiver le bouton
window.addEventListener('pageshow', function() {
    document.getElementById('loginBtn').disabled = false;
    document.getElementById('loginSpinner').classList.add('d-none');
});

// Focus sur le champ email au chargement
document.getElementById('email').focus();
</script>

<style>
.card {
    border: none;
    border-radius: 15px;
}

.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus {
    border-color: #dee2e6;
    box-shadow: none;
}

.input-group:focus-within {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    border-radius: 0.375rem;
}

.input-group:focus-within .input-group-text,
.input-group:focus-within .form-control,
.input-group:focus-within .btn {
    border-color: #86b7fe;
}

#togglePassword {
    border-left: none;
    background-color: #f8f9fa;
}

#togglePassword:hover {
    background-color: #e9ecef;
}

.btn-primary {
    border-radius: 10px;
    font-weight: 500;
}

.alert {
    border-radius: 10px;
}

.shadow {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
</style>

<?php include '../includes/footer.php'; ?>