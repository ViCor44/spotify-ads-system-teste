<?php
// public/templates/footer.php
?>
        </div> <!-- Fim de .main-content -->
    </div> <!-- Fim de .container -->

    <!-- O nosso leitor de anúncios principal -->
    <audio id="adPlayer"></audio>
    
    <!-- NOVO: Leitor de áudio invisível apenas para o som do "gong" -->
    <audio id="gongPlayer" src="assets/gong.mp3" preload="auto"></audio>

    <!-- Ligação para o nosso ficheiro de scripts (com cache-busting via filemtime) -->
    <script src="assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: time() ?>"></script>
    <script src="assets/js/tts.js?v=<?= @filemtime(__DIR__ . '/../assets/js/tts.js') ?: time() ?>"></script>
</body>
</html>
