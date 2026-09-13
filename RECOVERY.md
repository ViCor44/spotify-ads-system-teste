# Instalação e recuperação do Spot Master

Este projeto inclui diagnóstico, instalação da base de dados, backup e restauro. Os comandos abaixo devem ser executados no PowerShell, dentro da pasta do projeto.

## Preparar agora

1. Ligue um disco externo ou escolha uma pasta sincronizada e protegida.
2. Crie regularmente um backup completo:

   ```powershell
   .\backup-system.ps1 -Destination "E:\Backups\SpotMaster"
   ```

3. Confirme o sistema depois de alterações ou atualizações:

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
   .\restore-system.ps1 -BackupFile "E:\Backups\SpotMaster\spot-master-backup-AAAAMMDD-HHMMSS.zip"
   ```

4. Registe o robô para correr automaticamente a cada minuto:

   ```powershell
   .\register-robot-task.ps1
   ```

5. Abra `http://127.0.0.1/spotify-ads-system-teste/public/` e faça um anúncio de teste.

Se a pasta do projeto ou o endereço mudar, atualize `SPOTIFY_REDIRECT_URI` em `config/database.php` e indique exatamente o mesmo endereço no Spotify Developer Dashboard.

## Instalação sem backup

Execute:

```powershell
.\setup-new-pc.ps1
```

Na primeira execução é criado `config/database.php`. Preencha os dados do MySQL, Spotify e ElevenLabs e execute novamente:

```powershell
.\setup-new-pc.ps1 -RegisterTask
```

O instalador instala as dependências Composer, cria a base de dados e as tabelas, prepara as pastas graváveis, cria os anúncios de fecho iniciais e regista o robô quando pedido.

Para Google TTS, coloque o ficheiro privado da conta de serviço em `config/google-tts-sa.json`. Este ficheiro é incluído nos backups, mas nunca no Git.

## Operação e diagnóstico

Executar uma verificação manual:

```powershell
.\run_checker.bat
```

Remover a tarefa automática:

```powershell
.\register-robot-task.ps1 -Uninstall
```

No Agendador de Tarefas do Windows, a tarefa chama-se `Spot Master - Verificar agendamentos`. O painel considera o robô saudável quando `public/robot_heartbeat.log` foi atualizado nos últimos três minutos.

## Rotina recomendada

- Backup diário para outro disco ou armazenamento sincronizado.
- Manter pelo menos os últimos 7 backups.
- Uma vez por mês, testar o ZIP numa instalação separada.
- Depois de mudar credenciais, gerar imediatamente um novo backup.
- Se credenciais entrarem por engano no Git, revogá-las nos respetivos fornecedores; apagar apenas o ficheiro do commit não invalida a chave.