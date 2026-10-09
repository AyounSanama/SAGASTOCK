{{-- Mot de passe temporaire d'un compte créé ou réinitialisé : affiché une
     seule fois à l'administrateur (message de session éphémère), jamais
     conservé en clair. L'utilisateur le change à sa première connexion. --}}
@if(session('temporary_password'))
<dialog id="temporary-password-dialog" style="max-width:min(460px,calc(100% - 32px));border:0;border-radius:16px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <h2 style="margin:0 0 8px;font-size:20px">Mot de passe temporaire</h2>
    <p style="margin:0 0 14px;color:#5f6368">Transmettez-le à {{ session('temporary_password_for') ?: 'l’utilisateur' }}. Il ne sera plus affiché ; l’utilisateur devra le changer à sa première connexion.</p>
    <div style="display:flex;gap:8px;align-items:center">
        <code id="temporary-password-value" style="flex:1;padding:10px 12px;border:1px solid #d9dcd6;border-radius:10px;font-size:17px;letter-spacing:.5px;word-break:break-all">{{ session('temporary_password') }}</code>
        <button type="button" class="btn outline" data-copy-temporary-password>Copier</button>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:18px">
        <button type="button" class="btn primary" onclick="this.closest('dialog').close()">J’ai noté le mot de passe</button>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('temporary-password-dialog');
    if (!dialog || dialog.open) return;
    dialog.showModal();
    dialog.querySelector('[data-copy-temporary-password]').addEventListener('click', async (event) => {
        try {
            await navigator.clipboard.writeText(document.getElementById('temporary-password-value').textContent.trim());
            event.currentTarget.textContent = 'Copié';
        } catch (_) {
            event.currentTarget.textContent = 'Copie impossible';
        }
    });
})();
</script>
@endif
