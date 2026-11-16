<script lang="ts">
  import { onMount } from 'svelte';
  import Button from '../../../../components/Button.svelte';
  import Alert from '../../../../components/Alert.svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { boApi } from '../../../../api/session';
  import { boUsersCopy, validBoUserPassword, type BoUser, type BoUsersResponse } from '../../bo';
  import { passwordStrength } from '../../../../password/strength';

  let loading = $state(false);
  let busy = $state(false);
  let deleting = $state<number | null>(null);
  let editing = $state<number | null>(null);
  let users = $state<BoUser[]>([]);
  let email = $state('');
  let password = $state('');
  let confirmation = $state('');
  let editEmail = $state('');
  let editEnabled = $state(true);
  let error = $state('');
  let message = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(boUsersCopy(language));
  const strength = $derived(passwordStrength(password));
  const locale = $derived(language.toLowerCase().startsWith('fr') ? 'fr' : 'en');

  function explain(cause: unknown): string {
    if (cause instanceof ApiError) {
      if (cause.status === 401) {
        void goto(resolve('/login'), { replaceState: true });
        return t.session;
      }
      if (cause.status === 403) return t.forbidden;
      if (cause.status === 409) return t.conflict;
      if (cause.status === 400) return t.invalid;
    }
    return t.failure;
  }

  async function refresh(): Promise<void> {
    loading = true;
    error = '';
    try {
      const result = await boApi<BoUsersResponse>('/apibo/users');
      users = Array.isArray(result.data) ? result.data : [];
    } catch (cause) {
      error = explain(cause);
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    void refresh();
  });

  async function create(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (busy) return;
    error = '';
    message = '';
    if (password !== confirmation) {
      error = t.passwordMismatch;
      return;
    }
    if (!validBoUserPassword(password)) {
      error = t.invalidPassword;
      return;
    }
    busy = true;
    try {
      const result = await boApi<{ id: number; confirmation_sent: boolean }>('/apibo/users', {
        method: 'POST',
        body: JSON.stringify({ email: email.trim(), password, locale })
      });
      email = '';
      password = '';
      confirmation = '';
      message = result.confirmation_sent ? t.created : t.created + ' ' + t.mailPending;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  function edit(user: BoUser): void {
    editing = user.id;
    editEmail = user.email;
    editEnabled = user.enabled;
    message = '';
    error = '';
  }

  async function save(id: number): Promise<void> {
    if (busy) return;
    busy = true;
    error = '';
    message = '';
    try {
      const result = await boApi<{ confirmation_sent: boolean | null }>('/apibo/users/' + id, {
        method: 'PATCH',
        body: JSON.stringify({ email: editEmail.trim(), enabled: editEnabled, locale })
      });
      editing = null;
      message = result.confirmation_sent === false ? t.updated + ' ' + t.mailPending : t.updated;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function remove(id: number): Promise<void> {
    if (deleting !== null || !window.confirm(t.confirmDelete)) return;
    deleting = id;
    error = '';
    message = '';
    try {
      await boApi<{ status: string }>('/apibo/users/' + id, { method: 'DELETE' });
      if (editing === id) editing = null;
      message = t.deleted;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      deleting = null;
    }
  }
</script>

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>
<div class="users">
        <section class="panel">
          <h1>{t.heading}</h1>
          <p>{t.intro}</p>
          {#if error}<Alert kind="error">{error}</Alert>{/if}
          {#if message}<Alert kind="success">{message}</Alert>{/if}
          <form class="create-form" onsubmit={create}>
            <label for="user-email">{t.email}</label>
            <input id="user-email" type="email" bind:value={email} maxlength="254" autocomplete="off" required />
            <label for="user-password">{t.password}</label>
            <input id="user-password" type="password" bind:value={password}
              autocomplete="new-password" maxlength="1024" required />
            <small>{t.passwordRule}</small>
            <meter min="0" max="4" value={strength.score} aria-label={t.strength}></meter>
            <small aria-live="polite">{t.strength}: {t.strengthLevels[strength.score]}</small>
            <small>{t.strengthHint}</small>
            <label for="user-password-confirm">{t.confirmPassword}</label>
            <input id="user-password-confirm" type="password" bind:value={confirmation}
              autocomplete="new-password" maxlength="1024" required />
            <Button type="submit" disabled={busy}>{busy ? t.creating : t.create}</Button>
          </form>
        </section>
        <section class="panel">
          <div class="section-heading"><h2>{t.list}</h2>
            <Button variant="secondary" type="button" onclick={refresh} disabled={loading}>{t.refresh}</Button>
          </div>
          {#if loading}<p role="status">{t.loading}</p>{/if}
          {#if !loading && users.length === 0}<p>{t.none}</p>{/if}
          {#if users.length > 0}
            <div class="table-container">
              <table>
                <thead><tr><th>{t.email}</th><th>{t.emailState}</th><th>{t.state}</th><th>{t.actions}</th></tr></thead>
                <tbody>
                  {#each users as user (user.id)}
                    <tr>
                      <td>
                        {#if editing === user.id}
                          <label class="sr-only" for={'edit-email-' + user.id}>{t.email}</label>
                          <input id={'edit-email-' + user.id} type="email" bind:value={editEmail} maxlength="254" required />
                        {:else}
                          {user.email}
                        {/if}
                      </td>
                      <td>{user.email_verified ? t.verified : t.unverified}</td>
                      <td>
                        {#if editing === user.id}
                          <label class="sr-only" for={'edit-active-' + user.id}>{t.state}</label>
                          <select id={'edit-active-' + user.id} bind:value={editEnabled}>
                            <option value={true}>{t.enabled}</option>
                            <option value={false}>{t.disabled}</option>
                          </select>
                        {:else}
                          {user.enabled ? t.enabled : t.disabled}
                        {/if}
                      </td>
                      <td>
                        {#if editing === user.id}
                          <Button type="button" disabled={busy} onclick={() => save(user.id)}>{t.save}</Button>
                          <Button variant="secondary" type="button" onclick={() => editing = null}>{t.cancel}</Button>
                        {:else}
                          <Button variant="secondary" type="button" onclick={() => edit(user)}>{t.edit}</Button>
                          <Button variant="secondary" type="button" disabled={deleting !== null}
                            onclick={() => remove(user.id)}>{t.delete}</Button>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </section>
      </div>

<style>
  .users { max-width: 1200px; margin: auto; display: grid; gap: 22px; }
  .panel { background: white; border: 1px solid #e2e7ee; border-radius: 14px; padding: 28px; }
  h1, h2 { margin-top: 0; }
  p, small { color: #667085; }
  meter { display: block; width: 100%; height: 13px; }
  .create-form { display: grid; grid-template-columns: minmax(160px, 1fr); gap: 10px; max-width: 500px; }
  label { font-weight: 650; }
  input, select { padding: 12px; border: 1px solid #ccd4df; border-radius: 8px; font: inherit; }
  .section-heading { display: flex; justify-content: space-between; align-items: center; }
  .table-container { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; margin-top: 14px; text-align: left; }
  td, th { padding: 12px; border-bottom: 1px solid #e2e7ee; }
  th { color: #667085; font-size: .8rem; }
  .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
  @media (max-width: 700px) { .panel { padding: 18px; } }
</style>
