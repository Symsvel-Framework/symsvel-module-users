<script lang="ts">
  import { onMount } from 'svelte';
  import { resolve } from '$app/paths';
  import { api } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { registrationCopy, validRegistrationPassword } from '../../registration';
  import { passwordStrength } from '../../../../password/strength';

  let email = $state('');
  let password = $state('');
  let confirmation = $state('');
  let busy = $state(false);
  let submitted = $state(false);
  let error = $state('');
  let accountLanguage = $state('');
  let languageOptions = $state<{ iso_code: string; name: string; primary: boolean }[]>([]);
  let languagesError = $state(false);
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(registrationCopy(language));
  const strength = $derived(passwordStrength(password));

  onMount(() => {
    void (async () => {
      try {
        const result = await api<{ data: { iso_code: string; name: string; primary: boolean }[] }>(
          '/apiopen/languages/account-options', { public: true }
        );
        languageOptions = Array.isArray(result.data) ? result.data : [];
        accountLanguage = languageOptions.find((item) => item.primary)?.iso_code ?? languageOptions[0]?.iso_code ?? '';
        languagesError = languageOptions.length === 0;
      } catch {
        languagesError = true;
      }
    })();
  });

  function currentLanguage(): string {
    if (languageOptions.length === 1) {
      return languageOptions.find((item) => item.primary)?.iso_code ?? languageOptions[0].iso_code;
    }
    return languageOptions.some((item) => item.iso_code === accountLanguage) ? accountLanguage
      : (languageOptions.find((item) => item.primary)?.iso_code ?? '');
  }

  async function register(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (busy || languageOptions.length === 0) return;
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
          language: currentLanguage(),
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
        {#if languageOptions.length > 1}
          <label for="register-language">{t.accountLanguage}</label>
          <select id="register-language" bind:value={accountLanguage} required>
            {#each languageOptions as item (item.iso_code)}
              <option value={item.iso_code}>{item.name} ({item.iso_code})</option>
            {/each}
          </select>
        {/if}
        {#if languagesError}<p class="alert" role="alert">{t.failure}</p>{/if}
        <label for="register-password">{t.password}</label>
        <input id="register-password" type="password" autocomplete="new-password" bind:value={password} maxlength="1024" required />
        <small>{t.passwordHint}</small>
        <meter min="0" max="4" value={strength.score} aria-label={t.strength} />
        <small aria-live="polite">{t.strength}: {t.strengthLevels[strength.score]}</small>
        <small>{t.strengthHint}</small>
        <label for="register-confirmation">{t.confirmation}</label>
        <input id="register-confirmation" type="password" autocomplete="new-password" bind:value={confirmation} maxlength="1024" required />
        <button type="submit" disabled={busy || languageOptions.length === 0}>{busy ? t.pending : t.submit}</button>
      </form>
      <p><a href={resolve('/login')}>{t.signIn}</a></p>
    {/if}
  </section>
</main>

<style>
  meter { display: block; width: 100%; height: 13px; }
  small { color: #667085; }
</style>
