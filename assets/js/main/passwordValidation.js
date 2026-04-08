$(document).ready(function() {
  const password = $("#password");
  const confirm_password = $("#password_repeat");
  const result = $("#result");
  const matchMsg = $("#password_match_message");

  if (confirm_password.length === 0) {
    return;
  }

  // Empêche le collage dans le champ de confirmation
  confirm_password.bind('paste', function(e) {
    e.preventDefault();
  });

  /**
   * Vérifie que les deux mots de passe sont identiques
   * et affiche le message visuel sous le champ
   */
  function validatePassword() {
    matchMsg.removeClass(); // reset des classes avant mise à jour

    if (confirm_password.val().length > 0) {
      if (password.val() !== confirm_password.val()) {
        confirm_password[0].setCustomValidity("Les mots de passe ne sont pas identiques.");
        matchMsg.addClass('invalid').text("Les mots de passe ne sont pas identiques.").show();
      } else {
        confirm_password[0].setCustomValidity('');
        matchMsg.addClass('valid').text("Les mots de passe correspondent.").show();
      }
    } else {
      matchMsg.text("").hide();
    }
  }

  password.on("change keyup", function() {
    validatePassword();
    validateFormatPassword();
    result.html(checkStrength(password.val()));
  });

  confirm_password.on("keyup", function() {
    validatePassword();
  });

  /**
   * Vérifie le format général du mot de passe
   * - Minuscule, Majuscule, Chiffre, Caractère spécial
   * - Longueur ≥ 12
   */
  function validateFormatPassword() {
    const reg = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/;

    if (reg.test(password.val())) {
      password[0].setCustomValidity('');
    } else {
      password[0].setCustomValidity("Le mot de passe n'est pas assez sécurisé.");
    }
  }

  /**
   * Évalue la force du mot de passe
   */
  function checkStrength(password) {
    let strength = 0;

    if (password.length < 12) {
      result.removeClass().addClass('short');
      return 'Mot de passe trop court';
    }

    if (password.length >= 12) strength += 1;
    if (password.match(/(?=.*[a-z])(?=.*[A-Z])/)) strength += 1;
    if (password.match(/\d/)) strength += 1;
    if (password.match(/[^A-Za-z0-9\s]/)) strength += 1;

    result.removeClass();
    if (strength < 2) {
      result.addClass('weak');
      return 'Niveau faible';
    } else if (strength === 2) {
      result.addClass('good');
      return 'Niveau bon';
    } else {
      result.addClass('strong');
      return 'Niveau fort';
    }
  }

  /**
   * Vérifie chaque critère individuellement et affiche l’état visuel
   */
  $('input[type=password]#password').keyup(function() {
    const pswd = $(this).val();

    // Minuscule
    if (pswd.match(/[a-z]/)) {
      $('#pswd_letter').removeClass('invalid').addClass('valid');
    } else {
      $('#pswd_letter').removeClass('valid').addClass('invalid');
    }

    // Majuscule
    if (pswd.match(/[A-Z]/)) {
      $('#pswd_capital').removeClass('invalid').addClass('valid');
    } else {
      $('#pswd_capital').removeClass('valid').addClass('invalid');
    }

    // Chiffre
    if (pswd.match(/\d/)) {
      $('#pswd_number').removeClass('invalid').addClass('valid');
    } else {
      $('#pswd_number').removeClass('valid').addClass('invalid');
    }

    // Caractère spécial
    if (pswd.match(/[^A-Za-z0-9]/)) {
      $('#pswd_special_character').removeClass('invalid').addClass('valid');
    } else {
      $('#pswd_special_character').removeClass('valid').addClass('invalid');
    }

    // Longueur
    if (pswd.length < 12) {
      $('#pswd_length').removeClass('valid').addClass('invalid');
    } else {
      $('#pswd_length').removeClass('invalid').addClass('valid');
    }
  });
});
