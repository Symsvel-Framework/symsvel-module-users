<script lang="ts">
  import { getProjectBrand } from '../../../../branding';
  const brandName = getProjectBrand();
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { api, ApiError } from '../../../../api/client';
  import { appApi, logoutApp } from '../../../../api/session';
  import { uiLanguage } from '../../../../api/locales';
  import { passwordStrength } from '../../../../password/strength';
  import PasswordFeedback from '../../../../password/PasswordFeedback.svelte';
  import {
    appProfileCopy, type AppSelfProfile, type AppSelfProfileResponse, type ProfileLanguage
  } from '../../profile';

  let profile = $state<AppSelfProfile | null>(null);
  let languages = $state<ProfileLanguage[]>([]);
  let selected = $state('');
  let newEmail = $state('');
  let emailPassword = $state('');
  let emailBusy = $state(false);
  let emailError = $state('');
  let emailNotice = $state('');
  let currentPassword = $state('');
  let newPassword = $state('');
  let confirmPassword = $state('');
  let passwordBusy = $state(false);
  let passwordError = $state('');
  let passwordNotice = $state('');
  const passwordPolicy = $derived(passwordStrength(newPassword));
  let loading = $state(true);
  let saving = $state(false);
  let error = $state('');
  let notice = $state('');
  const locale = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'fr' : navigator.language));
  const t = $derived(appProfileCopy(locale));

  function explain(cause: unknown): string {
    if (cause instanceof ApiError && cause.status === 401) {
      void goto(resolve('/login'), { replaceState: true });
      return t.session;
    }
    return t.failure;
  }
  async function load(): Promise<void> {
    loading = true;
    error = '';
    try {
      const account = await appApi<AppSelfProfileResponse>('/api/users/profile');
      profile = account.data;
      try {
        const choices = await api<{ data: ProfileLanguage[] }>('/apiopen/languages/account-options', { public: true });
        languages = Array.isArray(choices.data) ? choices.data : [];
      } catch {
        // The personal account remains accessible if the language catalog is unavailable.
        languages = [];
      }
      selected = profile.language ?? languages.find((item) => item.primary)?.iso_code ?? '';
    } catch (cause) {
      error = explain(cause);
    } finally {
      loading = false;
    }
  }
  onMount(() => { void load(); });

  async function save(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (saving || !profile || !languages.some((item) => item.iso_code === selected)) return;
    saving = true;
    error = '';
    notice = '';
    try {
      const updated = await appApi<AppSelfProfileResponse>('/api/users/profile', {
        method: 'PATCH', body: JSON.stringify({ language: selected })
      });
      profile = updated.data;
      notice = t.saved;
    } catch (cause) {
      error = explain(cause);
    } finally {
      saving = false;
    }
  }


  async function changeEmail(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (emailBusy || !profile) return;
    emailError = '';
    emailNotice = '';
    if (!newEmail.includes('@') || newEmail.trim().toLowerCase() === profile.email.toLowerCase()) {
      emailError = t.emailChangeInvalid;
      return;
    }
    emailBusy = true;
    try {
      await appApi<{ status: string }>('/api/users/profile/email-change', {
        method: 'POST',
        body: JSON.stringify({
          new_email: newEmail.trim(),
          current_password: emailPassword,
          locale: locale.toLowerCase().startsWith('fr') ? 'fr' : 'en'
        })
      });
      newEmail = '';
      emailPassword = '';
      emailNotice = t.emailSent;
    } catch (cause) {
      emailError = cause instanceof ApiError && cause.status === 403
        ? t.emailChangeInvalid : t.emailChangeUnavailable;
    } finally {
      emailBusy = false;
    }
  }

  async function changePassword(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (passwordBusy) return;
    passwordError = '';
    passwordNotice = '';
    if (newPassword !== confirmPassword) {
      passwordError = t.passwordMismatch;
      return;
    }
    if (!passwordPolicy.valid || newPassword === currentPassword) {
      passwordError = newPassword === currentPassword ? t.passwordInvalid : t.passwordLength;
      return;
    }
    passwordBusy = true;
    try {
      await appApi<{ status: string }>('/api/users/profile/password', {
        method: 'POST',
        body: JSON.stringify({ current_password: currentPassword, new_password: newPassword })
      });
      currentPassword = '';
      newPassword = '';
      confirmPassword = '';
      passwordNotice = t.passwordSuccess;
      await logoutApp();
      await goto(resolve('/login'), { replaceState: true });
    } catch (cause) {
      passwordError = cause instanceof ApiError && cause.status === 403 ? t.passwordInvalid : explain(cause);
    } finally {
      passwordBusy = false;
    }
  }

</script>

<svelte:head><title>{t.title} | {$brandName} App</title></svelte:head>
<main class="auth-shell">
  <section class="auth-card profile-card">
    <header><strong>{t.title}</strong><span>App</span></header>
    <p>{t.intro}</p>
    {#if error}<p class="alert" role="alert">{error}</p>{/if}
    {#if notice}<p class="success" role="status">{notice}</p>{/if}
    {#if loading}<p role="status">{t.loading}</p>{/if}
    {#if profile}
      <dl>
        <div><dt>{t.email}</dt><dd>{profile.email}</dd></div>
        <div><dt>{t.verification}</dt><dd>{profile.email_verified ? t.verified : t.unverified}</dd></div>
      </dl>
      <form class="form" onsubmit={save}>
        <label for="profile-language">{t.language}</label>
        <select id="profile-language" bind:value={selected} disabled={languages.length < 2 || saving} required>
          {#each languages as item (item.iso_code)}
            <option value={item.iso_code}>{item.name} ({item.iso_code})</option>
          {/each}
        </select>
        {#if languages.length > 1}
          <button type="submit" disabled={saving || !selected || selected === profile.language}>
            {saving ? t.busy : t.save}
          </button>
        {/if}
      </form>
      {#if languages.length < 2}<p>{t.singleLanguage}</p>{/if}

      <div class="profile-divider"></div>
      <form class="form" onsubmit={changeEmail}>
        <h2>{t.emailChangeTitle}</h2>
        {#if emailError}<p class="alert" role="alert">{emailError}</p>{/if}
        {#if emailNotice}<p class="success" role="status">{emailNotice}</p>{/if}
        <label for="user-email-new">{t.newEmail}</label>
        <input id="user-email-new" type="email" autocomplete="email" bind:value={newEmail}
          required maxlength="254" disabled={emailBusy} />
        <label for="user-email-password">{t.emailCurrentPassword}</label>
        <input id="user-email-password" type="password" autocomplete="current-password"
          bind:value={emailPassword} required disabled={emailBusy} />
        <p>{t.emailChangeHelp}</p>
        <button type="submit" disabled={emailBusy || !newEmail || !emailPassword}>
          {t.sendEmailConfirmation}
        </button>
      </form>
      <div class="profile-divider"></div>
      <form class="form" onsubmit={changePassword}>
        <h2>{t.passwordTitle}</h2>
        {#if passwordError}<p class="alert" role="alert">{passwordError}</p>{/if}
        {#if passwordNotice}<p class="success" role="status">{passwordNotice}</p>{/if}
        <label for="user-password-current">{t.currentPassword}</label>
        <input id="user-password-current" type="password" autocomplete="current-password" required
          bind:value={currentPassword} disabled={passwordBusy} />
        <label for="user-password-next">{t.newPassword}</label>
        <input id="user-password-next" type="password" autocomplete="new-password" maxlength="1024"
          bind:value={newPassword} required disabled={passwordBusy} />
        <PasswordFeedback password={newPassword} />
        <label for="user-password-confirm">{t.confirmPassword}</label>
        <input id="user-password-confirm" type="password" autocomplete="new-password" maxlength="1024"
          bind:value={confirmPassword} required disabled={passwordBusy} />
        <button type="submit" disabled={passwordBusy || !currentPassword || !passwordPolicy.valid || newPassword === currentPassword || newPassword !== confirmPassword}>
          {t.passwordSave}
        </button>
      </form>
    {/if}
    <p><a href={resolve('/dashboard')}>{t.back}</a></p>
  </section>
</main>

<style>
  .profile-card { width: min(100%, 600px); }
  dl { display: grid; gap: 12px; margin: 22px 0; }
  dl div { display: grid; gap: 3px; }
  dt { font-weight: 650; color: #667085; }
  dd { margin: 0; overflow-wrap: anywhere; }
  .form select { width: 100%; padding: 12px; border: 1px solid #ccd4df; border-radius: 9px; font: inherit; }
  .profile-divider { border-top: 1px solid #d7dce5; margin: 20px 0; }
  .form input { width: 100%; box-sizing: border-box; }
  .success { padding: 11px 13px; background: #edf9f2; color: #166243; border-radius: 9px; }
</style>
