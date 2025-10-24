<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guards.php';
require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/wall.php';

$cfg = require __DIR__ . '/auth/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

$pdo = db();
$me  = optional_auth() ?? null;

/** Events laden – Spalten passend zu deinem Schema */
$events = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, slug, name, titelbild, starts_at, ends_at, status
        FROM tournaments
        WHERE status IN ('open','running','locked','scoring')
          AND starts_at <= NOW()
          AND ends_at   >= NOW()
        ORDER BY starts_at ASC
        LIMIT 5
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $events = [];
}

ob_start();
?>


<style>
/* Container für den Feed verschwommen darstellen */
.feed-blur {
  position: relative;
  filter: blur(6px);          /* Stärke des Blur-Effekts */
  pointer-events: none;       /* verhindert Interaktion */
  user-select: none;          /* kein Text markieren */
}

/* Overlay-Text auf dem Feed */
.feed-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: #fff;
  z-index: 10;
}

.feed-overlay h2 {
  font-size: 1.8rem;
  margin-bottom: 1rem;
}

.feed-overlay .btn {
  background: #e53935;
  border: none;
  padding: 10px 18px;
  border-radius: 6px;
  font-weight: 600;
  color: #fff;
  margin: 0 5px;
  cursor: pointer;
}
.feed-overlay .btn:hover {
  background: #c62828;
}
.wall-blur-container {
  position: relative;
}

.wall-content {
  filter: blur(6px);
  pointer-events: none; /* Gast kann nicht interagieren */
}

.wall-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #fff;
  background: rgba(0, 0, 0, 0.6); /* leichter Overlay-Dunkel */
  z-index: 10;
  padding: 20px;
}

.wall-overlay h2 {
  font-size: 1.8rem;
  margin-bottom: 10px;
}

.wall-overlay p {
  font-size: 1.1rem;
  margin-bottom: 20px;
  color: #ccc;
}

.wall-buttons {
  display: flex;
  gap: 15px;
}

.wall-buttons .btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: bold;
  text-decoration: none;
  transition: background 0.2s;
}

.btn-login {
  background: #444;
  color: #fff;
}
.btn-login:hover {
  background: #666;
}

.btn-register {
  background: #c00;
  color: #fff;
}
.btn-register:hover {
  background: #e00;
}

</style>














<main>

            <!-- community page section start -->
            <section class="section-py overflow-visible">
                <div class="container pt-[30px]">
                    <div class="grid grid-cols-12 gap-30p">
                      		<div class="min-[1480px]:col-span-3 xl:col-span-4 xl:block hidden relative">
                            <div class="xl:sticky xl:top-30 h-screen pb-40 overflow-y-auto scrollbar-0">
                                <div class="py-24p px-32p bg-b-neutral-3 rounded-12 mb-30p">
                                    <div class="flex flex-wrap justify-between items-center gap-20p mb-24p">
                                        <div class="flex-y gap-3">
                                            <span class="badge badge-circle size-3 badge-primary"></span>
                                            <h4 class="heading-4 text-w-neutral-1">
                                                Recent Topics
                                            </h4>
                                        </div>
                                        <a href="#" class="text-s-medium text-w-neutral-1 link-1">
                                            View All
                                            <i class="ti ti-arrow-right"></i>
                                        </a>
                                    </div>
                                    <div class="grid grid-cols-1 gap-24p">
                        <?php include __DIR__ . '/wall/sidebar-left.php'; ?>
                          
              
                         
                                    </div>
                                </div>
                                
                            </div>
                        </div>	
                        <div class="min-[1480px]:col-span-6 xl:col-span-8 col-span-12">
                            <div class="grid grid-cols-1 gap-30p">
                                <div>
                                    <div class="feed-blur swiper stories-carousel">
                                        <div class="swiper-wrapper">
                                            <!-- card 1 -->
                                            <div class="swiper-slide">
                                                <div class="relative w-full rounded-12 group">
                                                    <div class="overflow-hidden rounded-12">
                                                        <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                                                            src="assets/images/stories/story1.png" alt="story" />
                                                        <span
                                                            class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">1</span>
                                                    </div>
                                                    <div
                                                        class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
                                                        <div>
                                                            <h6
                                                                class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">
                                                                Marvin McKinney Controls for Life Support Services
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- card 2 -->
                                            <div class="swiper-slide">
                                                <div class="relative w-full rounded-12 group">
                                                    <div class="overflow-hidden rounded-12">
                                                        <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                                                            src="assets/images/stories/story2.png" alt="story" />
                                                        <span
                                                            class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">2</span>
                                                    </div>
                                                    <div
                                                        class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
                                                        <div>
                                                            <h6
                                                                class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">
                                                                Cameron Williamson Controls for Life Support Services
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- card 3 -->
                                            <div class="swiper-slide">
                                                <div class="relative w-full rounded-12 group">
                                                    <div class="overflow-hidden rounded-12">
                                                        <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                                                            src="assets/images/games/game10.png" alt="story" />
                                                        <span
                                                            class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">3</span>
                                                    </div>
                                                    <div
                                                        class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
                                                        <div>
                                                            <h6
                                                                class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">
                                                                Game Center for the first time in the game
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- card 1 -->
                                            <div class="swiper-slide">
                                                <div class="relative w-full rounded-12 group">
                                                    <div class="overflow-hidden rounded-12">
                                                        <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                                                            src="assets/images/stories/story1.png" alt="story" />
                                                        <span
                                                            class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">1</span>
                                                    </div>
                                                    <div
                                                        class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
                                                        <div>
                                                            <h6
                                                                class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">
                                                                Marvin McKinney Controls for Life Support Services
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- card 2 -->
                                            <div class="swiper-slide">
                                                <div class="relative w-full rounded-12 group">
                                                    <div class="overflow-hidden rounded-12">
                                                        <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                                                            src="assets/images/stories/story2.png" alt="story" />
                                                        <span
                                                            class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">2</span>
                                                    </div>
                                                    <div
                                                        class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
                                                        <div>
                                                            <h6
                                                                class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">
                                                                Cameron Williamson Controls for Life Support Services
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- card 3 -->
                                            <div class="swiper-slide">
                                                <div class="relative w-full rounded-12 group">
                                                    <div class="overflow-hidden rounded-12">
                                                        <img class="w-full md:h-[300px] sm:h-[240px] xsm:h-[220px] h-[200px] object-cover group-hover:scale-110 transition-1"
                                                            src="assets/images/games/game10.png" alt="story" />
                                                        <span
                                                            class="absolute top-4 left-4 flex-c size-6 rounded-full bg-accent-4 text-w-neutral-1">3</span>
                                                    </div>
                                                    <div
                                                        class="w-full absolute inset-0 flex flex-col justify-end z-[2] p-24p overlay-7">
                                                        <div>
                                                            <h6
                                                                class="heading-6 text-w-neutral-1 line-clamp-2 max-w-[90px]">
                                                                Game Center for the first time in the game
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <form class="feed-blur bg-b-neutral-3 rounded-12 px-32p py-20p">
                                    <div class="flex-y gap-3.5 mb-24p">
                                        <img class="shrink-0 avatar size-60p" src="assets/images/users/user1.png"
                                            alt="user" />
                                        <div class="w-full">
                                            <input
                                                class="w-full bg-b-neutral-2 text-sm text-w-neutral-1 placeholder:text-w-neutral-4 rounded-32 py-16p px-24p"
                                                type="text" name="post" id="post" placeholder="What’s Your Mind ?" />
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-around gap-3">
                                        <label for="media" class="flex-y gap-3 cursor-pointer">
                                            <span
                                                class="shrink-0 flex-c size-48p rounded-full bg-secondary/20 text-secondary icon-24">
                                                <i class="ti ti-photo"></i>
                                            </span>
                                            <span class="text-s-medium text-w-neutral-1">Photo/Video</span>
                                            <input type="file" id="media" class="hidden" />
                                        </label>
                                        <button type="button" class="flex-y gap-3 cursor-pointer">
                                            <span
                                                class="shrink-0 flex-c size-48p rounded-full bg-primary/20 text-primary icon-24">
                                                <i class="ti ti-users"></i>
                                            </span>
                                            <span class="text-s-medium text-w-neutral-1">Tag Friend</span>
                                        </button>
                                        <button type="button" class="flex-y gap-3 cursor-pointer">
                                            <span
                                                class="shrink-0 flex-c size-48p rounded-full bg-accent-4/20 text-accent-4 icon-24">
                                                <i class="ti ti-mood-smile-beam"></i>
                                            </span>
                                            <span class="text-s-medium text-w-neutral-1">Fealing /Activity</span>
                                        </button>
                                    </div>
                                </form>
                                <div>
                                    <div
                                        class="grid grid-cols-1 gap-30p *:bg-b-neutral-3 *:rounded-12 *:px-40p *:py-32p">

                                        <!-- post card 1 -->
                                        

                                        <!-- post card 2 -->
                                        

                                        <!-- post card 3 -->
                                       <div class="wall-blur-container">
  <div class="wall-content">
    <!-- hier lädt dein echter Feed (unscharf gemacht via CSS) -->
  </div>
  <div class="wall-overlay">
    <h2>Die Community-Wall ist nur für Mitglieder sichtbar</h2>
    <p>Melde dich an oder registriere dich, um Beiträge zu sehen.</p>
    <div class="wall-buttons">
      <a href="/login.php" class="btn btn-login">Login</a>
      <a href="/register.php" class="btn btn-register">Registrieren</a>
    </div>
  </div>
</div>

                                        <!-- post card 4 -->
                                        <div data-aos="fade-up" class="feed-blur">
                                            <div class="flex items-center justify-between flex-wrap gap-3">
                                                <div class="flex items-center gap-3">
                                                    <img class="avatar size-60p" src="assets/images/users/user7.png"
                                                        alt="user" />
                                                    <div>
                                                        <a href="profile.html"
                                                            class="text-xl-medium text-w-neutral-1 link-1 line-clamp-1 mb-1">
                                                            Malan Willam
                                                        </a>
                                                        <span class="text-s-medium text-w-neutral-4">
                                                            7 day ago
                                                        </span>
                                                    </div>
                                                </div>
                                                <div x-data="dropdown" class="dropdown">
                                                    <button @click="toggle()"
                                                        class="dropdown-toggle w-fit text-white icon-32">
                                                        <i class="ti ti-dots"></i>
                                                    </button>
                                                    <div x-show="isOpen" @click.away="close()" class="dropdown-content">
                                                        <button @click="close()" class="dropdown-item">Save
                                                            Link</button>
                                                        <button @click="close()" class="dropdown-item">Report</button>
                                                        <button @click="close()" class="dropdown-item">Hide
                                                            Post</button>
                                                        <button @click="close()" class="dropdown-item">Block
                                                            User</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- post content area -->
                                            <div class="py-20p">
                                                <p class="text-sm text-w-neutral-4">
                                    hier der themencontent
                                                </p>
                                            </div>

                                            <!-- post footer -->
                                            <div x-data="{ commentsShow: false }" @click.outside="commentsShow = false">
                                                <div class="flex items-center justify-between flex-wrap gap-24p mb-20p">
                                                    <div class="flex items-center gap-32p">
                                                        <button type="button" class="flex items-center gap-2 text-base
                                                text-w-neutral-1">
                                                            <i class="ti ti-heart icon-24 text-w-neutral-4"></i>
                                                            Like
                                                        </button>
                                                        <button type="button" @click="commentsShow =  !commentsShow"
                                                            class="flex items-center gap-2 text-base
                                            text-w-neutral-1">
                                                            <i class="ti ti-message icon-24 text-w-neutral-4"></i>
                                                            Comment
                                                        </button>
                                                    </div>
                                                    <button type="button" class="flex items-center gap-2 text-base
                                        text-w-neutral-1">
                                                        <i class="ti ti-share-3 icon-24 text-w-neutral-4"></i>
                                                        Share
                                                    </button>
                                                </div>
                                                <div class="flex items-center flex-wrap gap-3 md:gap-[18px] mb-20p">
                                                    <div
                                                        class="flex items-center *:avatar *:size-8 *:border *:border-white *:-ml-3 ml-3">
                                                        <img src="assets/images/users/avatar1.png" alt="user" />
                                                        <img src="assets/images/users/avatar2.png" alt="user" />
                                                        <img src="assets/images/users/avatar3.png" alt="user" />
                                                        <img src="assets/images/users/avatar4.png" alt="user" />
                                                    </div>
                                                    <p class="text-sm text-w-neutral-4">
                                                        Liked
                                                        <span class="span text-w-neutral-1">Johnson</span>
                                                        <span class="span text-w-neutral-1">and</span>
                                                        209 Others
                                                    </p>
                                                </div>

                                                <!-- comments area -->
                                                <div>
                                                    <div class="pt-20p border-t border-shap">
                                                        <div class="grid grid-cols-1 gap-20p mb-20p">
                                                            <!-- comment 1 -->
                                                            
                                                            <!-- comment 2 -->
                                                            <div class="flex items-start gap-3">
                                                                <img class="avatar size-48p"
                                                                    src="assets/images/users/user3.png" alt="user" />
                                                                <div>
                                                                    <div class="bg-glass-5 px-3 py-2 rounded-12">
                                                                        <a href="profile.html"
                                                                            class="text-m-medium text-w-neutral-1 link-1 line-clamp-1 mb-2">
                                                                            Jon Smith
                                                                        </a>
                                                                        <div
                                                                            class="flex items-end max-sm:flex-wrap gap-3">
                                                                            <p class="text-sm text-w-neutral-3">
                                                                       kommentar 2
                                                                            </p>
                                                                            <button type="button"
                                                                                class="shrink-0 flex items-end gap-2 icon-20 text-w-neutral-4">
                                                                                <i class="ti ti-heart"></i>
                                                                                <i class="ti ti-mood-smile-beam"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex items-center gap-16p">
                                                                        <button type="button" class="flex-y gap-1">
                                                                            <i
                                                                                class="ti ti-heart icon-20 text-danger"></i>
                                                                            <span
                                                                                class="text-sm text-w-neutral-1">Like</span>
                                                                        </button>
                                                                        <div class="flex-y gap-1">
                                                                            <button type="button"
                                                                                class="text-sm text-w-neutral-1">
                                                                                Reply
                                                                            </button>
                                                                            <span class="text-sm text-w-neutral-1">
                                                                                5D
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                          

                                                        <div class="mt-20p">
                                             
                                                            <form
                                                                class="flex items-center justify-between gap-24p bg-b-neutral-2  rounded-full py-16p px-32p">
                                                                <input
                                                                    class="w-full bg-transparent text-sm text-white placeholder:text-w-neutral-1"
                                                                    type="text" name="name"
                                                                    placeholder="Add Your Comment..." />
                                                                <div class="flex-y gap-3 icon-24 text-w-neutral-4">
                                                                    <button type="button">
                                                                        <i class="ti ti-mood-smile-beam"></i>
                                                                    </button>
                                                                    <label for="comment-media-5">
                                                                        <i class="ti ti-photo"></i>
                                                                    </label>
                                                                    <button>
                                                                        <i class="ti ti-link"></i>
                                                                    </button>
                                                                    <input type="file" name="comment-media"
                                                                        id="comment-media-5" class="hidden" />
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- post card 5 -->
                                        <div data-aos="fade-up" class="feed-blur">
                                            <div class="flex items-center justify-between flex-wrap gap-3">
                                                <div class="flex items-center gap-3">
                                                    <img class="avatar size-60p" src="assets/images/users/user7.png"
                                                        alt="user" />
                                                    <div>
                                                        <a href="profile.html"
                                                            class="text-xl-medium text-w-neutral-1 link-1 line-clamp-1 mb-1">
                                                            Malan Willam
                                                        </a>
                                                        <span class="text-s-medium text-w-neutral-4">
                                                            7 day ago
                                                        </span>
                                                    </div>
                                                </div>
                                                <div x-data="dropdown" class="dropdown">
                                                    <button @click="toggle()"
                                                        class="dropdown-toggle w-fit text-white icon-32">
                                                        <i class="ti ti-dots"></i>
                                                    </button>
                                                    <div x-show="isOpen" @click.away="close()" class="dropdown-content">
                                                        <button @click="close()" class="dropdown-item">Save
                                                            Link</button>
                                                        <button @click="close()" class="dropdown-item">Report</button>
                                                        <button @click="close()" class="dropdown-item">Hide
                                                            Post</button>
                                                        <button @click="close()" class="dropdown-item">Block
                                                            User</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- post content area -->
                                            <div class="py-20p">
                                                <p class="text-sm text-w-neutral-4">
                                    hier der themencontent
                                                </p>
                                            </div>

                                            <!-- post footer -->
                                            <div x-data="{ commentsShow: false }" @click.outside="commentsShow = false">
                                                <div class="flex items-center justify-between flex-wrap gap-24p mb-20p">
                                                    <div class="flex items-center gap-32p">
                                                        <button type="button" class="flex items-center gap-2 text-base
                                                text-w-neutral-1">
                                                            <i class="ti ti-heart icon-24 text-w-neutral-4"></i>
                                                            Like
                                                        </button>
                                                        <button type="button" @click="commentsShow =  !commentsShow"
                                                            class="flex items-center gap-2 text-base
                                            text-w-neutral-1">
                                                            <i class="ti ti-message icon-24 text-w-neutral-4"></i>
                                                            Comment
                                                        </button>
                                                    </div>
                                                    <button type="button" class="flex items-center gap-2 text-base
                                        text-w-neutral-1">
                                                        <i class="ti ti-share-3 icon-24 text-w-neutral-4"></i>
                                                        Share
                                                    </button>
                                                </div>
                                                <div class="flex items-center flex-wrap gap-3 md:gap-[18px] mb-20p">
                                                    <div
                                                        class="flex items-center *:avatar *:size-8 *:border *:border-white *:-ml-3 ml-3">
                                                        <img src="assets/images/users/avatar1.png" alt="user" />
                                                        <img src="assets/images/users/avatar2.png" alt="user" />
                                                        <img src="assets/images/users/avatar3.png" alt="user" />
                                                        <img src="assets/images/users/avatar4.png" alt="user" />
                                                    </div>
                                                    <p class="text-sm text-w-neutral-4">
                                                        Liked
                                                        <span class="span text-w-neutral-1">Johnson</span>
                                                        <span class="span text-w-neutral-1">and</span>
                                                        209 Others
                                                    </p>
                                                </div>

                                                <!-- comments area -->
                                                <div>
                                                    <div class="pt-20p border-t border-shap">
                                                        <div class="grid grid-cols-1 gap-20p mb-20p">
                                                            <!-- comment 1 -->
                                                            
                                                            <!-- comment 2 -->
                                                            <div class="flex items-start gap-3">
                                                                <img class="avatar size-48p"
                                                                    src="assets/images/users/user3.png" alt="user" />
                                                                <div>
                                                                    <div class="bg-glass-5 px-3 py-2 rounded-12">
                                                                        <a href="profile.html"
                                                                            class="text-m-medium text-w-neutral-1 link-1 line-clamp-1 mb-2">
                                                                            Jon Smith
                                                                        </a>
                                                                        <div
                                                                            class="flex items-end max-sm:flex-wrap gap-3">
                                                                            <p class="text-sm text-w-neutral-3">
                                                                       kommentar 2
                                                                            </p>
                                                                            <button type="button"
                                                                                class="shrink-0 flex items-end gap-2 icon-20 text-w-neutral-4">
                                                                                <i class="ti ti-heart"></i>
                                                                                <i class="ti ti-mood-smile-beam"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex items-center gap-16p">
                                                                        <button type="button" class="flex-y gap-1">
                                                                            <i
                                                                                class="ti ti-heart icon-20 text-danger"></i>
                                                                            <span
                                                                                class="text-sm text-w-neutral-1">Like</span>
                                                                        </button>
                                                                        <div class="flex-y gap-1">
                                                                            <button type="button"
                                                                                class="text-sm text-w-neutral-1">
                                                                                Reply
                                                                            </button>
                                                                            <span class="text-sm text-w-neutral-1">
                                                                                5D
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                          

                                                        <div class="mt-20p">
                                             
                                                            <form
                                                                class="flex items-center justify-between gap-24p bg-b-neutral-2  rounded-full py-16p px-32p">
                                                                <input
                                                                    class="w-full bg-transparent text-sm text-white placeholder:text-w-neutral-1"
                                                                    type="text" name="name"
                                                                    placeholder="Add Your Comment..." />
                                                                <div class="flex-y gap-3 icon-24 text-w-neutral-4">
                                                                    <button type="button">
                                                                        <i class="ti ti-mood-smile-beam"></i>
                                                                    </button>
                                                                    <label for="comment-media-5">
                                                                        <i class="ti ti-photo"></i>
                                                                    </label>
                                                                    <button>
                                                                        <i class="ti ti-link"></i>
                                                                    </button>
                                                                    <input type="file" name="comment-media"
                                                                        id="comment-media-5" class="hidden" />
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="flex-c mt-48p">
                                        <button type="button" class="btn btn-lg btn-neutral-3 rounded-12">
                                            Load more...
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                       <div class="min-[1480px]:col-span-3 relative min-[1480px]:block hidden">
                            <div
                                class="min-[1480px]:sticky min-[1480px]:top-30 h-screen pb-40 overflow-y-auto scrollbar-0">
                                <div class="grid grid-cols-12 gap-24p *:bg-b-neutral-3 *:rounded-12 *:px-32p *:py-24p">
                                    <div class="xxl:col-span-12 md:col-span-6 col-span-12 xxl:order-1 order-3">
                                        <div class="flex flex-wrap items-center justify-between gap-3 mb-40p">
                                            <h4 class="heading-4 text-w-neutral-1 ">
                                                Active Members
                                            </h4>
                                            <a href="#" class="inline-flex items-center gap-3 text-w-neutral-1 link-1">
                                                Zeige Alle
                                                <i class="ti ti-arrow-right"></i>
                                            </a>
                                        </div>
                                        <!-- active-members-carousel -->
                                        <div class="swiper active-members-carousel w-full">
                                            <div class="swiper-wrapper *:w-fit">
						
                        <?php include __DIR__ . '/wall/sidebar-right.php'; ?>
						
						
						
						
						
						
						
						
						                 </div>
                                        </div>
                                    </div>
									
									
									
									
									
									
									
			<?php include __DIR__ . '/wall/sidebar-events.php'; ?>						
									



	
									
									
									
									
									
									
									
									
									
									
									
									
                                    
                                   
                       
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- community page section end -->

        </main>





















<script>
  window.REACTIONS_PNG = {
    like: '/assets/images/reactions/like.png',
    love: '/assets/images/reactions/love.png',
    haha: '/assets/images/reactions/haha.png',
    wow: '/assets/images/reactions/wow.png',
    sad: '/assets/images/reactions/sad.png',
    angry: '/assets/images/reactions/angry.png'
  };
</script>


<script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>
<script src="<?= esc($APP_BASE) ?>/assets/js/wall.js?v=1" defer></script>
<script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>
<script src="<?= esc($APP_BASE) ?>/assets/js/wall.reactions.js" defer></script>

<script>
document.addEventListener("DOMContentLoaded", async () => {
  const feed = document.getElementById("feed");
  if (!feed) return;

  try {
    const url = feed.dataset.endpoint;
    const res = await fetch(url, { credentials: "same-origin" });
    const text = await res.text();
    const j = JSON.parse(text);

    if (j?.ok && Array.isArray(j.posts)) {
      j.posts.forEach(p => {
        feed.insertAdjacentHTML("beforeend", p.html);
      });
    }

    // Blur sofort anwenden
    feed.style.filter = "blur(6px)";
    feed.style.pointerEvents = "none";
    feed.style.userSelect = "none";

  } catch (e) {
    console.error("Feed laden fehlgeschlagen", e);
  }
});
</script>


<?php
$html = ob_get_clean();
render_theme_page($html, 'Wall – Gästeansicht');
