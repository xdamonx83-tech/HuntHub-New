<?php
declare(strict_types=1);

// Lade die Kern-Bibliotheken, genau wie in deinen anderen Seiten
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../auth/guards.php';

// Stelle sicher, dass der Nutzer eingeloggt ist
$me = optional_auth();


// Lade die Sprachdateien
$lang = function_exists('detect_lang') ? detect_lang() : 'de';
$GLOBALS['L'] = load_lang($lang);
$L = $GLOBALS['L'];

// Starte den Output-Buffer, um den gesamten Seiteninhalt zu sammeln
ob_start();
?>

<!-- Externe Bibliotheken für Video-Player und Cropper -->
<link href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/nouislider/distribute/nouislider.min.css" rel="stylesheet">

<!-- Eigene Stile für die Wall -->
<link rel="stylesheet" href="/wall/assets/wall.css">

<div class="wall-container">
    <main class="wall-main">
        <!-- Box zum Erstellen eines neuen Posts -->
        <div class="card create-post-card">
            <form id="create-post-form" enctype="multipart/form-data">
                 <div class="post-header">
                    <img src="<?php echo htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/images/avatars/placeholder.png'); ?>" alt="Dein Avatar" class="avatar">
                    <textarea name="post_text" id="post-text" placeholder="Was gibt's Neues, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Gast'); ?>?"></textarea>
                </div>

                <!-- Vorschau für hochgeladene Medien -->
                <div id="media-preview-container"></div>

                <div class="post-actions">
                    <div class="action-buttons">
                        <label for="image-upload" class="action-button">
                            <i class="fas fa-image"></i> Bild
                            <input type="file" id="image-upload" name="media_file" accept="image/*">
                        </label>
                        <label for="video-upload" class="action-button">
                            <i class="fas fa-video"></i> Video
                             <input type="file" id="video-upload" name="media_file" accept="video/*">
                        </label>
                    </div>
                    <button type="submit" id="submit-post-btn" class="submit-btn" disabled>Posten</button>
                </div>
                 <input type="hidden" name="media_type" id="media-type-input">
                 <input type="hidden" name="video_start" id="video-start-input">
                 <input type="hidden" name="video_end" id="video-end-input">
            </form>
        </div>

        <!-- Feed für die Posts -->
        <div id="wall-feed">
            <!-- Posts werden hier per JavaScript geladen -->
            <div class="loading-spinner"></div>
        </div>
        <button id="load-more-btn" style="display:none;">Mehr laden</button>
    </main>

    <aside class="wall-sidebar">
        <!-- Platz für zukünftige Widgets wie "Freunde", "Gruppen" etc. -->
        <div class="card">
            <h4>Freunde</h4>
            <p>Coming soon...</p>
        </div>
         <div class="card">
            <h4>Gruppen</h4>
            <p>Coming soon...</p>
        </div>
    </aside>
</div>

<!-- Modal für das Video-Trimming -->
<div id="video-trim-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Video zuschneiden</h2>
        <video id="video-to-trim" controls></video>
        <div id="video-trim-slider"></div>
        <div class="trim-info">
            Start: <span id="trim-start-time">0s</span> | Ende: <span id="trim-end-time">0s</span>
        </div>
        <button id="confirm-trim-btn" class="submit-btn">Zuschnitt bestätigen</button>
    </div>
</div>


<!-- JavaScript-Bibliotheken -->
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/nouislider/distribute/nouislider.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/de.min.js"></script>


<!-- Eigenes JavaScript für die Wall -->
<script src="/wall/assets/wall.js"></script>

<?php
// Sammle den gesamten gepufferten Inhalt
$content = ob_get_clean();

// Definiere die Metadaten für die Seite
$pageTitle = "Deine Wall | Hunthub";
$pageDesc  = "Teile Posts, Bilder und Videos mit der Community.";
$pageImage = "/assets/og/default.webp"; // Ein Standard-Bild für Social Media Previews

// Rufe die zentrale Render-Funktion auf, die das komplette HTML mit Header und Footer erstellt
render_theme_page($content, $pageTitle, $pageDesc, $pageImage);


