<script lang="ts">
  import { resolve } from '$app/paths';
  import { api } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { registrationCopy, validRegistrationPassword } from '../../registration';

  let email = $state('');
  let password = $state('');
  let confirmation = $state('');
  let busy = $state(false);
  let submitted = $state(false);
  let error = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(registrationCopy(language));

  async function register(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (busy) return;
    error = '';
    if (password !== confirmation) {
      error = t.mismatch;
      return;
    }
    if (!validRegistrationPassword(password)) {
      error = t.invalidPassword;
      return;
    }
    busy = true;
    try {
      await api<{ status: string }>('/apiopen/users/register', {
        public: true,
        method: 'POST',
        body: JSON.stringify({
          email: email.trim(),
          password,
          locale: language.toLowerCase().startsWith('fr') ? 'fr' : 'en'
        })
      });
      password = '';
      confirmation = '';
      submitted = true;
    } catch {
      error = t.failure;
    } finally {
      busy = false;
    }
  }
</script>

<svelte:head><title>{t.title} | SymSvel App</title></svelte:head>
<main class="auth-shell">
  <section class="auth-card">
    <header><strong>SymSvel</strong><span>App</span></header>
    <h1>{t.title}</h1>
    <p>{t.intro}</p>
    {#if submitted}
      <p role="status">{t.success}</p>
      <p><a href={resolve('/login')}>{t.signIn}</a></p>
      <p><a href={resolve('/verify-email')}>{t.resend}</a></p>
    {:else}
      {#if error}<p class="alert" role="alert">{error}</p>{/if}
      <form class="form" onsubmit={register}>
        <label for="register-email">{t.email}</label>
        <input id="register-email" type="email" autocomplete="email" bind:value={email} maxlength="254" required />
        <label for="register-password">{t.password}</label>
        <input id="register-password" type="password" autocomplete="new-password" bind:value={password} minlength="12" maxlength="1024" required />
        <small>{t.passwordHint}</small>
        <label for="register-confirmation">{t.confirmation}</label>
        <input id="register-confirmation" type="password" autocomplete="new-password" bind:value={confirmation} minlength="12" maxlength="1024" required />
        <button type="submit" disabled={busy}>{busy ? t.pending : t.submit}</button>
      </form>
      <p><a href={resolve('/login')}>{t.signIn}</a></p>
    {/if}
  </section>
</main>
