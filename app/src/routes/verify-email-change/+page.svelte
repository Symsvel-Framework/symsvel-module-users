<script lang="ts">
  import { onMount } from 'svelte';
  import { resolve } from '$app/paths';
  import { api } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { appProfileCopy } from '../../profile';

  let busy = $state(false);
  let success = $state(false);
  let error = $state(false);
  const locale = $derived($uiLanguage ?? 'fr');
  const t = $derived(appProfileCopy(locale));

  onMount(() => {
    const token = new URLSearchParams(window.location.search).get('token');
    window.history.replaceState(window.history.state, '', window.location.pathname + window.location.hash);
    if (!token || !/^[A-Za-z0-9_-]{43}$/.test(token)) {
      error = true;
      return;
    }
    busy = true;
    void api<{ status: string }>('/apiopen/auth/profile/email-change/complete', {
      public: true,
      method: 'POST',
      body: JSON.stringify({ token })
    }).then((result) => {
      success = result.status === 'completed';
      if (!success) error = true;
    }).catch(() => { error = true; }).finally(() => { busy = false; });
  });
</script>

<svelte:head>
  <title>{t.emailVerificationTitle} | SymSvel App</title>
  <meta name="referrer" content="no-referrer" />
</svelte:head>
<main class="auth-shell">
  <section class="auth-card">
    <header><strong>SymSvel</strong><span>App</span></header>
    <h1>{t.emailVerificationTitle}</h1>
    {#if busy}<p role="status">{t.emailVerificationBusy}</p>{/if}
    {#if success}<p role="status">{t.emailVerificationSuccess}</p>{/if}
    {#if error}<p class="alert" role="alert">{t.emailVerificationFailed}</p>{/if}
    <p><a href={resolve('/login')}>{t.backToLogin}</a></p>
  </section>
</main>
