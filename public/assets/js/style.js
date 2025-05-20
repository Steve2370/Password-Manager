document.addEventListener('DOMContentLoaded', function() {
    initTooltips();

    setupPasswordToggles();

    setupPasswordStrengthMeter();

    setupPasswordGenerator();

    setupClipboardActions();

    if (document.getElementById('passwordStrengthChart')) {
        initCharts();
    }
    animateElements();
});

function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl =>
        new bootstrap.Tooltip(tooltipTriggerEl)
    );
}

function setupPasswordToggles() {
    const toggleButtons = document.querySelectorAll('.toggle-password');

    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            const icon = this.querySelector('i');
            if (type === 'text') {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

function setupPasswordStrengthMeter() {
    const passwordInputs = document.querySelectorAll('input[type="password"]:not(#confirm_password):not(#generated-password)');

    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            const meterContainer = document.querySelector('.password-strength-meter');
            if (!meterContainer) return;

            const progressBar = meterContainer.querySelector('.progress-bar');
            const feedback = meterContainer.querySelector('.password-feedback');

            const password = this.value;
            const strength = calculatePasswordStrength(password);

            progressBar.style.width = `${strength.score * 25}%`;
            progressBar.className = 'progress-bar ' + strength.class;

            feedback.innerHTML = `<i class="fas ${strength.icon} me-1"></i>${strength.message}`;
            feedback.className = 'password-feedback mt-1 ' + strength.textClass;
        });
    });
}

function calculatePasswordStrength(password) {
    if (!password) {
        return {
            score: 0,
            message: 'Entrez un mot de passe',
            class: 'bg-secondary',
            textClass: 'text-muted',
            icon: 'fa-info-circle'
        };
    }

    let score = 0;

    if (password.length > 8) score++;
    if (password.length > 12) score++;

    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    score = Math.min(Math.floor(score / 1.5), 4);

    const strengthInfo = [
        {
            message: 'Très faible',
            class: 'bg-danger',
            textClass: 'password-very-weak',
            icon: 'fa-exclamation-circle'
        },
        {
            message: 'Faible',
            class: 'bg-warning',
            textClass: 'password-weak',
            icon: 'fa-exclamation-triangle'
        },
        {
            message: 'Moyen',
            class: 'bg-info',
            textClass: 'password-medium',
            icon: 'fa-info-circle'
        },
        {
            message: 'Fort',
            class: 'bg-success',
            textClass: 'password-strong',
            icon: 'fa-check-circle'
        },
        {
            message: 'Très fort',
            class: 'bg-success',
            textClass: 'password-strong',
            icon: 'fa-shield-alt'
        }
    ];

    return {
        score: score,
        ...strengthInfo[score]
    };
}

function setupPasswordGenerator() {
    const generateBtn = document.querySelector('.generate-password');
    const lengthRange = document.getElementById('password-length');
    const lengthValue = document.getElementById('length-value');
    const generatedField = document.getElementById('generated-password');

    if (!generateBtn) return;

    if (lengthRange && lengthValue) {
        lengthRange.addEventListener('input', function() {
            lengthValue.textContent = this.value;
        });
    }

    generateBtn.addEventListener('click', function() {
        const length = parseInt(lengthRange.value);
        const includeUppercase = document.getElementById('include-uppercase').checked;
        const includeLowercase = document.getElementById('include-lowercase').checked;
        const includeNumbers = document.getElementById('include-numbers').checked;
        const includeSymbols = document.getElementById('include-symbols').checked;

        const password = generatePassword(length, includeUppercase, includeLowercase, includeNumbers, includeSymbols);
        generatedField.value = password;

        const progressBar = document.querySelector('.password-strength .progress-bar');
        const feedback = document.querySelector('.password-feedback');

        const strength = calculatePasswordStrength(password);
        progressBar.style.width = `${strength.score * 25}%`;
        progressBar.className = 'progress-bar ' + strength.class;
        feedback.innerHTML = `<i class="fas ${strength.icon} me-1"></i>${strength.message}`;
        feedback.className = 'password-feedback small ' + strength.textClass;
    });

    document.getElementById('passwordGeneratorOptions').addEventListener('hidden.bs.collapse', function() {
        if (generatedField.value) {
            document.getElementById('passwordValue').value = generatedField.value;

            const event = new Event('input', { bubbles: true });
            document.getElementById('passwordValue').dispatchEvent(event);
        }
    });
}

function generatePassword(length, useUppercase, useLowercase, useNumbers, useSymbols) {
    let chars = '';
    const uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
    const numberChars = '0123456789';
    const symbolChars = '!@#$%^&*()-_=+[]{}|;:,.<>?';

    if (useUppercase) chars += uppercaseChars;
    if (useLowercase) chars += lowercaseChars;
    if (useNumbers) chars += numberChars;
    if (useSymbols) chars += symbolChars;

    if (!chars) {
        chars = uppercaseChars + lowercaseChars + numberChars;
    }

    let password = '';
    const charsLength = chars.length;

    for (let i = 0; i < length; i++) {
        password += chars.charAt(Math.floor(Math.random() * charsLength));
    }

    return password;
}

function setupClipboardActions() {
    const copyButtons = document.querySelectorAll('.copy-password');

    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            passwordInput.select();
            document.execCommand('copy');

            const originalTitle = this.getAttribute('data-bs-original-title');
            this.setAttribute('data-bs-original-title', 'Copié!');

            const tooltip = bootstrap.Tooltip.getInstance(this);
            tooltip.show();

            setTimeout(() => {
                this.setAttribute('data-bs-original-title', originalTitle);
                tooltip.hide();
            }, 1000);
        });
    });
}

function initCharts() {
    const ctx = document.getElementById('passwordStrengthChart').getContext('2d');

    const passwordStrengthChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Fort', 'Moyen', 'Faible', 'Très faible'],
            datasets: [{
                data: [8, 3, 1, 0],
                backgroundColor: [
                    'rgba(28, 200, 138, 0.8)',
                    'rgba(54, 185, 204, 0.8)',
                    'rgba(246, 194, 62, 0.8)',
                    'rgba(231, 74, 59, 0.8)'
                ],
                borderColor: [
                    'rgba(28, 200, 138, 1)',
                    'rgba(54, 185, 204, 1)',
                    'rgba(246, 194, 62, 1)',
                    'rgba(231, 74, 59, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw} mots de passe (${Math.round((context.raw / 12) * 100)}%)`;
                        }
                    }
                }
            }
        }
    });
}

function animateElements() {
    const elements = document.querySelectorAll('.auth-card, .stats-card, .dashboard-card, .benefit-item');

    elements.forEach((element, index) => {
        element.classList.add('fade-in');
        element.style.animationDelay = `${index * 0.1}s`;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const savePasswordBtn = document.getElementById('savePassword');
    if (savePasswordBtn) {
        savePasswordBtn.addEventListener('click', function() {
            const form = document.getElementById('addPasswordForm');

            if (!validatePasswordForm()) {
                return;
            }
            const modal = bootstrap.Modal.getInstance(document.getElementById('addPasswordModal'));
            modal.hide();

            showNotification('Mot de passe enregistré avec succès!', 'success');
        });
    }
});

function validatePasswordForm() {
    const name = document.getElementById('passwordName').value;
    const category = document.getElementById('passwordCategory').value;
    const username = document.getElementById('passwordUsername').value;
    const password = document.getElementById('passwordValue').value;

    if (!name || !category || !username || !password) {
        showNotification('Veuillez remplir tous les champs obligatoires', 'danger');
        return false;
    }

    return true;
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `toast align-items-center text-white bg-${type} border-0`;
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'assertive');
    notification.setAttribute('aria-atomic', 'true');

    notification.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
        </div>
    `;

    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }

    container.appendChild(notification);

    const toast = new bootstrap.Toast(notification, {
        autohide: true,
        delay: 3000
    });

    toast.show();

    notification.addEventListener('hidden.bs.toast', function() {
        notification.remove();
    });
}