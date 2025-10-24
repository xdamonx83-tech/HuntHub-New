<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/db.php';
$pdo = db();

// Nur laufende oder offene Turniere
$sql = "SELECT id, slug, name, titelbild, starts_at, ends_at
        FROM tournaments
        WHERE status IN ('open','running')
        ORDER BY starts_at ASC
        LIMIT 5";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="xxl:col-span-12 md:col-span-6 col-span-12 xxl:order-2 order-2">
                                        <div class="flex items-center justify-between flex-wrap gap-3 mb-24p">
                                            <h4 class="heading-4 text-w-neutral-1 ">
                                                Aktive Events
                                            </h4>
                                    
                                        </div>
										<?php foreach ($rows as $e): ?>
                                        <div class="group">
                                            <div class="overflow-hidden rounded-12">
                                                <img class="w-full h-[202px] object-cover group-hover:scale-110 transition-1"
                                                    src="<?= $e['titelbild'] ? esc($e['titelbild']) : '/assets/images/default-event.png' ?>" alt="img" />
                                            </div>
                                            <div class="flex-y justify-between flex-wrap gap-20px py-3">
                                                <div class="flex-y gap-3">
                                                    <div class="flex-y gap-1">
                                                        <i class="ti ti-heart icon-20 text-danger"></i>
                                                        <span class="text-sm text-w-neutral-1">
                                                            <?= date('d.m.Y H:i', strtotime($e['starts_at'])) ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex-y gap-1">
                                                        <i class="ti ti-message icon-20 text-primary"></i>
                                                        <span class="text-sm text-w-neutral-1">
                                                            <?= date('d.m.Y H:i', strtotime($e['ends_at'])) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                       
                                            </div>
                               
                                            <a href="/tournaments/view.php?id=<?= (int)$e['id'] ?>"
                                                class="heading-5 text-w-neutral-1 line-clamp-2 link-1">
                                                <?= esc($e['name']) ?>
                                            </a>
                                        </div>
										<?php endforeach; ?>
                                    </div>

