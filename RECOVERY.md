# Instalação e recuperação do Spot Master

Este projeto inclui diagnóstico, instalação da base de dados, backup e restauro. Os comandos abaixo devem ser executados no PowerShell, dentro da pasta do projeto.

## Preparar agora

1. Configure o Google Drive para sincronizar a pasta `C:\BackupMySQL`.
2. Faça duplo clique em `instalar_backup_diario.bat`. A tarefa será executada diariamente às 23:00 e manterá os cinco backups mais recentes.
3. Para criar um backup manual completo, faça duplo clique em `criar_backup_agora.bat`.

   Em alternativa, execute:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\backup-system.ps1" -Destination "C:\BackupMySQL" -RetentionCount 5
   ```

4. Confirme o sistema depois de alterações ou atualizações:

   ```powershell
   .\setup-new-pc.ps1 -CheckOnly
   ```

O ZIP contém a base de dados, áudios, definições e credenciais. Deve ser guardado fora deste computador e tratado como confidencial. O código fica no repositório Git e não precisa de ser duplicado no ZIP.

## Requisitos do PC novo

- Windows 10 ou 11.
- XAMPP com Apache, MySQL e PHP 8.0 ou superior.
- Composer.
- Extensões PHP `curl`, `fileinfo`, `json`, `mbstring`, `openssl` e `pdo_mysql` ativas no `php.ini`.
- Acesso à Internet para Spotify, ElevenLabs, Google e instalação Composer.

No painel do XAMPP, inicie Apache e MySQL. Para manter os serviços disponíveis depois de reiniciar o PC, instale-os como serviços pelo próprio painel do XAMPP.

## Recuperar após uma avaria

1. Instale os requisitos acima.
2. Abra o PowerShell em `C:\xampp\htdocs` e obtenha o projeto:

   ```powershell
   git clone https://github.com/ViCor44/spotify-ads-system-teste.git
   cd spotify-ads-system-teste
   ```

3. Restaure o ZIP mais recente:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\restore-system.ps1" -BackupFile "C:\BackupMySQL\spot-master-backup-AAAAMMDD-HHMMSS.zip"
   ```

4. Registe o robô para correr automaticamente a cada minuto:

   Faça duplo clique em `instalar_robot.bat`. O instalador regista a tarefa e testa imediatamente o robô.

5. Abra `http://127.0.0.1/spotify-ads-system-teste/public/` e faça um anúncio de teste.

Se a pasta do projeto ou o endereço mudar, atualize `SPOTIFY_REDIRECT_URI` em `config/database.php` e indique exatamente o mesmo endereço no Spotify Developer Dashboard.

## Instalação sem backup

Execute:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\setup-new-pc.ps1"
```

Na primeira execução é criado `config/database.php`. Preencha os dados do MySQL, Spotify e ElevenLabs e execute novamente:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\setup-new-pc.ps1" -RegisterTask
```

O instalador instala as dependências Composer, cria a base de dados e as tabelas, prepara as pastas graváveis, cria os anúncios de fecho iniciais e regista o robô quando pedido.

Para Google TTS, coloque o ficheiro privado da conta de serviço em `config/google-tts-sa.json`. Este ficheiro é incluído nos backups, mas nunca no Git.

## Operação e diagnóstico

Executar uma verificação manual:

```powershell
.\run_checker.bat
```

Se o Dashboard indicar que o robô está offline, faça duplo clique em `instalar_robot.bat` e atualize a página após a mensagem de sucesso. As verificações automáticas usam `php-win.exe` e decorrem silenciosamente, sem abrir uma janela a cada minuto.

Remover a tarefa automática:

```powershell
.\register-robot-task.ps1 -Uninstall
```

No Agendador de Tarefas do Windows, a tarefa chama-se `Spot Master - Verificar agendamentos`. O painel considera o robô saudável quando `public/robot_heartbeat.log` foi atualizado nos últimos três minutos.

A tarefa de cópia de segurança chama-se `Spot Master - Backup diario`. Para a remover:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\register-backup-task.ps1" -Uninstall
```

## Rotina recomendada

- Backup diário para `C:\BackupMySQL`, com essa pasta sincronizada pelo Google Drive.
- A tarefa mantém automaticamente os últimos 5 backups.
- Uma vez por mês, testar o ZIP numa instalação separada.
- Depois de mudar credenciais, gerar imediatamente um novo backup.
- Se credenciais entrarem por engano no Git, revogá-las nos respetivos fornecedores; apagar apenas o ficheiro do commit não invalida a chave.