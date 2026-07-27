# Configuration SMTP

SAGASTOCK ne stocke aucun mot de passe SMTP dans Git. En développement, le
transport `log` écrit les liens de réinitialisation dans `storage/logs`.

Pour activer les e-mails réels, renseigner uniquement dans `backend/.env` :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_USERNAME=adresse@example.org
MAIL_PASSWORD=secret-fourni-par-le-service
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=adresse@example.org
MAIL_FROM_NAME="SAGASTOCK"
```

Tester ensuite sans exposer le secret :

```powershell
php artisan sagastock:mail-test destinataire@example.org
```

Les valeurs définitives dépendent du fournisseur choisi par le porteur du
projet. Le compte doit utiliser un mot de passe d'application lorsque le
fournisseur l'exige.
