<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Button from '../../../../components/Button.svelte';
  import Alert from '../../../../components/Alert.svelte';
  import Modal from '../../../../components/Modal.svelte';
  import DataTable from '../../../../components/DataTable.svelte';
  import type { DataAction, DataColumn, SortOrder } from '../../../../components/data-table';
  import type { PageSize } from '../../../../components/pagination';
  import { ApiError } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { boApi } from '../../../../api/session';
  import { boUsersCopy, type BoUser, type BoUsersResponse } from '../../bo';

  let loading = $state(false);
  let busy = $state(false);
  let deleting = $state<number | null>(null);
  let resending = $state<number | null>(null);
  let editing = $state<BoUser | null>(null);
  let showCreate = $state(false);
  let users = $state<BoUser[]>([]);
  let email = $state('');
  let editEmail = $state('');
  let accountLanguage = $state('');
  let editLanguage = $state('');
  let activeLanguages = $state<{ iso_code: string; name: string; primary: boolean }[]>([]);
  let languagesError = $state('');
  let editEnabled = $state(true);
  let error = $state('');
  let message = $state('');
  let search = $state('');
  let sortKey = $state<string | null>('email');
  let sortOrder = $state<SortOrder>('asc');
  let page = $state(1);
  let limit = $state<PageSize>(20);

  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(boUsersCopy(language));
  const locale = $derived(language.toLowerCase().startsWith('fr') ? 'fr' : 'en');

  function selectedLanguage(code: string | null): string {
    return activeLanguages.length === 1
      ? (activeLanguages.find((item) => item.primary)?.iso_code ?? activeLanguages[0].iso_code)
      : (code && activeLanguages.some((item) => item.iso_code === code) ? code
        : (activeLanguages.find((item) => item.primary)?.iso_code ?? activeLanguages[0]?.iso_code ?? ''));
  }

  async function loadLanguages(): Promise<void> {
    languagesError = '';
    try {
      const response = await boApi<{ data: { iso_code: string; name: string; primary: boolean }[] }>('/apibo/languages/account-options');
      activeLanguages = Array.isArray(response.data) ? response.data : [];
      if (activeLanguages.length === 0) languagesError = t.failure;
    } catch (cause) {
      languagesError = explain(cause);
      activeLanguages = [];
    }
  }

  const columns = $derived<DataColumn<BoUser>[]>([
    { key: 'email', label: t.email, sortable: true },
    { key: 'email_verified', label: t.emailState,
      format: (v) => v ? t.verified : t.unverified },
    { key: 'enabled', label: t.state,
      format: (v) => v ? t.enabled : t.disabled },
    { key: 'created_at', label: t.createdAt, sortable: true,
      format: (v) => new Date(String(v)).toLocaleDateString(language) }
  ]);
  const actions = $derived<DataAction<BoUser>[]>([
    {
      key: 'edit', label: t.edit, onClick: (row) => edit(row)
    },
    {
      key: 'resend', label: (row) => row.email_verified ? t.verified : t.resend,
      disabled: (row) => row.email_verified || !row.enabled || resending !== null,
      onClick: (row) => { void resend(row.id); }
    },
    {
      key: 'delete', label: t.delete,
      disabled: () => deleting !== null,
      onClick: (row) => { void remove(row.id); }
    }
  ]);
  const filtered = $derived(
    [...users]
      .filter((user) => user.email.toLocaleLowerCase().includes(search.toLocaleLowerCase()))
      .sort((a, b) => {
        const key = sortKey === 'created_at' ? 'created_at' : 'email';
        return a[key].localeCompare(b[key], language, { sensitivity: 'base' }) *
          (sortOrder === 'asc' ? 1 : -1);
      })
  );
  const visible = $derived(filtered.slice((page - 1) * limit, page * limit));

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

  onMount(() => { void refresh(); void loadLanguages(); });

  function openCreate(): void {
    email = '';
    accountLanguage = selectedLanguage(null);
    error = '';
    showCreate = true;
  }

  async function create(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (busy) return;
    error = '';
    message = '';
    busy = true;
    try {
      const result = await boApi<{ id: number; invitation_sent: boolean }>('/apibo/users', {
        method: 'POST', body: JSON.stringify({ email: email.trim(), language: selectedLanguage(accountLanguage), locale })
      });
      showCreate = false;
      message = result.invitation_sent ? t.created : t.created + ' ' + t.mailPending;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  function edit(user: BoUser): void {
    editing = user;
    editEmail = user.email;
    editLanguage = selectedLanguage(user.language);
    editEnabled = user.enabled;
    message = '';
    error = '';
  }

  async function save(): Promise<void> {
    if (busy || !editing) return;
    busy = true;
    error = '';
    message = '';
    try {
      const result = await boApi<{ confirmation_sent: boolean | null }>('/apibo/users/' + editing.id, {
        method: 'PATCH',
        body: JSON.stringify({ email: editEmail.trim(), enabled: editEnabled, language: selectedLanguage(editLanguage), locale })
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

  async function resend(id: number): Promise<void> {
    if (resending !== null) return;
    resending = id;
    error = '';
    message = '';
    try {
      const result = await boApi<{ invitation_sent: boolean }>(
        `/apibo/users/${id}/invitations/resend`, {
          method: 'POST', body: JSON.stringify({ locale })
        }
      );
      message = result.invitation_sent ? t.resent : t.mailPending;
    } catch (cause) {
      error = explain(cause);
    } finally {
      resending = null;
    }
  }

  async function remove(id: number): Promise<void> {
    if (deleting !== null || !window.confirm(t.confirmDelete)) return;
    deleting = id;
    error = '';
    message = '';
    try {
      await boApi<{ status: string }>('/apibo/users/' + id, { method: 'DELETE' });
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
  <header class="page-heading">
    <div><h1>{t.heading}</h1><p>{t.intro}</p></div>
    <Button type="button" onclick={openCreate}>{t.create}</Button>
  </header>
  {#if message}<Alert kind="success">{message}</Alert>{/if}
  {#if error && !showCreate && !editing}<Alert kind="error">{error}</Alert>{/if}
  <section class="panel">
    <div class="section-heading">
      <h2>{t.list}</h2>
      <Button type="button" variant="secondary" onclick={refresh} disabled={loading}>{t.refresh}</Button>
    </div>
    <DataTable rows={visible} {columns} {actions}
      {loading} emptyMessage={t.none}
      filters={[{ key: 'search', label: t.search, type: 'search', placeholder: t.searchHint }]}
      filterValues={{ search }}
      onFilterChange={(_, value) => { search = value; page = 1; }}
      {sortKey} {sortOrder}
      onSortChange={(key, order) => { sortKey = key; sortOrder = order; page = 1; }}
      pagination={{
        page, limit, total: filtered.length,
        onPageChange: (value) => page = value,
        onLimitChange: (value) => { limit = value; page = 1; }
      }} />
  </section>

  <Modal open={showCreate} title={t.create} onClose={() => showCreate = false}>
    <p>{t.inviteIntro}</p>
    {#if error}<Alert kind="error">{error}</Alert>{/if}
    <form class="modal-form" onsubmit={create}>
      <label for="user-email">{t.email}</label>
      <input id="user-email" type="email" bind:value={email} maxlength="254" autocomplete="email" required />
      {#if activeLanguages.length > 1}
        <label for="new-user-language">{t.accountLanguage}</label>
        <select id="new-user-language" bind:value={accountLanguage} required>
          {#each activeLanguages as item (item.iso_code)}
            <option value={item.iso_code}>{item.name} ({item.iso_code})</option>
          {/each}
        </select>
      {/if}
      {#if languagesError}<Alert kind="error">{languagesError}</Alert>{/if}
      <div class="modal-actions">
        <Button type="button" variant="secondary" onclick={() => showCreate = false}>{t.cancel}</Button>
        <Button type="submit" disabled={busy || activeLanguages.length === 0}>{busy ? t.creating : t.create}</Button>
      </div>
    </form>
  </Modal>

  <Modal open={editing !== null} title={t.edit} onClose={() => editing = null}>
    {#if editing}
      {#if error}<Alert kind="error">{error}</Alert>{/if}
      <form class="modal-form" onsubmit={(event) => { event.preventDefault(); void save(); }}>
        <label for="edit-user-email">{t.email}</label>
        <input id="edit-user-email" type="email" bind:value={editEmail} maxlength="254" required />
        {#if activeLanguages.length > 1}
        <label for="edit-user-language">{t.accountLanguage}</label>
        <select id="edit-user-language" bind:value={editLanguage} required>
          {#each activeLanguages as item (item.iso_code)}
            <option value={item.iso_code}>{item.name} ({item.iso_code})</option>
          {/each}
        </select>
      {/if}
      {#if languagesError}<Alert kind="error">{languagesError}</Alert>{/if}
      <label for="edit-user-active">{t.state}</label>
        <select id="edit-user-active" bind:value={editEnabled}>
          <option value={true}>{t.enabled}</option>
          <option value={false}>{t.disabled}</option>
        </select>
        <div class="modal-actions">
          <Button type="button" variant="secondary" onclick={() => editing = null}>{t.cancel}</Button>
          <Button type="submit" disabled={busy || activeLanguages.length === 0}>{busy ? t.saving : t.save}</Button>
        </div>
      </form>
    {/if}
  </Modal>
</div>

<style>
  .users { max-width: 1200px; margin: auto; display: grid; gap: 20px; }
  .page-heading, .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
  .page-heading h1, .section-heading h2 { margin: 0; }
  .page-heading p, .modal-form p { color: #667085; }
  .panel { background: white; border: 1px solid #e2e7ee; border-radius: 14px; padding: 24px; min-width: 0; }
  .section-heading { margin-bottom: 18px; }
  .modal-form { display: grid; gap: 10px; margin-top: 16px; }
  .modal-form label { font-weight: 650; }
  .modal-form input, .modal-form select { width: 100%; border: 1px solid #ccd4df; border-radius: 8px; padding: 11px; font: inherit; }
  .modal-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
</style>
