export default {
  "registration": {
    "title": "Create account",
    "intro": "Your account will be available after confirming your email address.",
    "email": "Email address",
    "accountLanguage": "Account language",
    "password": "Password",
    "confirmation": "Confirm password",
    "passwordHint": "12 to 1024 UTF-8 bytes, no NUL character.",
    "strength": "Password diversity",
    "strengthHint": "A long passphrase with varied characters is recommended but not required.",
    "mismatch": "Passwords do not match.",
    "invalidPassword": "Password must contain between 12 and 1024 bytes.",
    "submit": "Create my account",
    "pending": "Registering…",
    "success": "If your request was accepted, a confirmation email will be sent.",
    "failure": "Unable to process your request at this time.",
    "signIn": "Back to sign in",
    "resend": "Resend confirmation link",
    "strengthLevels": {
      "0": "Empty",
      "1": "Very low",
      "2": "Low",
      "3": "Moderate",
      "4": "Good"
    }
  },
  "activation": {
    "title": "Activate account",
    "intro": "Choose a password for your new account.",
    "password": "Password",
    "confirm": "Confirm password",
    "rule": "12 to 1024 UTF-8 bytes without NUL.",
    "strength": "Password strength",
    "mismatch": "Passwords do not match.",
    "invalid": "Password is invalid.",
    "expired": "Invitation invalid, expired or already used.",
    "failure": "Activation failed.",
    "submit": "Activate account",
    "busy": "Activating…",
    "success": "Your account is active. You can sign in.",
    "login": "Sign in",
    "strengths": {
      "0": "Empty",
      "1": "Very weak",
      "2": "Weak",
      "3": "Moderate",
      "4": "Strong"
    }
  }
} as const;
