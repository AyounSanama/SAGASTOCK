<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
class TestMail extends Command {protected $signature='sagastock:mail-test {email}';protected $description='Envoie un e-mail de test pour vérifier la configuration SMTP PharmaCare';public function handle():int{Mail::raw('Votre configuration de messagerie PharmaCare fonctionne correctement.',fn($message)=>$message->to($this->argument('email'))->subject('Test de messagerie PharmaCare'));$this->info('Message de test remis au transport configuré.');return self::SUCCESS;}}
