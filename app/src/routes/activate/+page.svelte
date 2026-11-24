<script lang="ts">
  import { getProjectBrand } from '../../../../branding';
  const brandName = getProjectBrand();
  import { onMount } from 'svelte';
  import { resolve } from '$app/paths';
  import { ApiError, api } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import PasswordFeedback from '../../../../password/PasswordFeedback.svelte';
  import { activationCopy, validActivationPassword } from '../../invitation';

  let token = $state<string | null>(null);
  let loaded = $state(false);
  let completed = $state(false);
  let password = $state('');
  let confirmation = $state('');
  let busy = $state(false);
  let error = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(activationCopy(language));

  onMount(() => {
    const candidate = new URLSearchParams(window.location.search).get('token');
    token = candidate && /^[A-Za-z0-9_-]{43}$/.test(candidate) ? candidate : null;
    // Keep the one-time secret out of history, screenshots and referrers.
    window.history.replaceState(window.history.state, '', window.location.pathname + window.location.hash);
    loaded = true;
  });

  async function activate(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (!token || busy) return;
    error = '';
    if (password !== confirmation) { error = t.mismatch; return; }
    if (!validActivationPassword(password)) { error = t.invalid; return; }
    busy = true;
    try {
      await api<{ status: 'completed' }>('/apiopen/auth/invitation/complete', {
        public: true, method: 'POST',
        body: JSON.stringify({ token, password })
      });
      password = '';
      confirmation = '';
      token = null;
      completed = true;
    } catch (cause) {
      error = cause instanceof ApiError && cause.status === 400 ? t.expired : t.failure;
    } finally {
      busy = false;
    }
  }
</script>

<svelte:head>
  <title>{t.title} | {$brandName} App</title>
  <meta name="referrer" content="no-referrer" />
</svelte:head>

<main class="auth-shell">
  <section class="auth-card">
    <header><strong>{$brandName}</strong><span>App</span></header>
    <h1>{t.title}</h1>
    {#if completed}
      <p role="status">{t.success}</p>
    {:else if loaded && !token}
      <p class="alert" role="alert">{t.expired}</p>
    {:else if loaded}
      <p>{t.intro}</p>
      {#if error}<p class="alert" role="alert">{error}</p>{/if}
      <form class="form" onsubmit={activate}>
        <label for="invited-password">{t.password}</label>
        <input id="invited-password" type="password" autocomplete="new-password"
          bind:value={password} maxlength="1024" required />
        <PasswordFeedback {password} />
        <label for="invited-confirm">{t.confirm}</label>
        <input id="invited-confirm" type="password" autocomplete="new-password"
          bind:value={confirmation} maxlength="1024" required />
        <button type="submit" disabled={busy}>{busy ? t.busy : t.submit}</button>
      </form>
    {/if}
    <p><a href={resolve('/login')}>{t.login}</a></p>
  </section>
</main>

