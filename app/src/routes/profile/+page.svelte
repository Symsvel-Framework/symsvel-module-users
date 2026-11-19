<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { api, ApiError } from '../../../../api/client';
  import { appApi } from '../../../../api/session';
  import { uiLanguage } from '../../../../api/locales';
  import {
    appProfileCopy, type AppSelfProfile, type AppSelfProfileResponse, type ProfileLanguage
  } from '../../profile';

  let profile = $state<AppSelfProfile | null>(null);
  let languages = $state<ProfileLanguage[]>([]);
  let selected = $state('');
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
      const [account, choices] = await Promise.all([
        appApi<AppSelfProfileResponse>('/api/users/profile'),
        api<{ data: ProfileLanguage[] }>('/apiopen/languages/account-options', { public: true })
      ]);
      profile = account.data;
      languages = Array.isArray(choices.data) ? choices.data : [];
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
</script>

<svelte:head><title>{t.title} | SymSvel App</title></svelte:head>
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
        <button type="submit" disabled={saving || !selected || selected === profile.language}>
          {saving ? t.busy : t.save}
        </button>
      </form>
      <p>{t.readonly}</p>
      <p><a href={resolve('/forgot-password')}>{t.password}</a></p>
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
  .success { padding: 11px 13px; background: #edf9f2; color: #166243; border-radius: 9px; }
</style>
