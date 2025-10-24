<div class="bg-b-neutral-3 py-40p px-32p rounded-12">
              
                                            <h3 class="heading-4 text-w-neutral-1 mb-3">
                                               Leaderboard
                                            </h3>
											
											
											
											     <?php if (empty($board)): ?>
        <div class="mut"><?php echo $L['no_runs']; ?></div>
      <?php else: ?>
											
                       <div class="overflow-x-auto scrollbar-sm">
                                <table class="min-w-full">
                                    <thead class="text-xl font-borda bg-transparent text-w-neutral-1 whitespace-nowrap">
								
                                        <tr>
                                            <th class="px-32p pb-20p text-left">
                                                <?php echo $L['name']; ?>
                                            </th>
                                         
                                            <th class="px-32p pb-20p text-left">
                                                <?php echo $L['place']; ?>
                                            </th>
                                            <th class="px-32p pb-20p text-left">
                                                <?php echo $L['points']; ?>
                                            </th>
											  <th class="px-32p pb-20p text-left">
                                                <?php echo $L['runs']; ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="text-base font-medium font-poppins text-w-neutral-1 divide-y-[12px] divide-b-neutral-4">
                                        <!-- Row Template -->
										<?php $rank=1; foreach ($board as $row): ?>
                                        <tr class="bg-b-neutral-3 hover:bg-b-neutral-2 transition-1 *:min-w-[220px]">
                                            <td class="px-32p py-3">
                                                <div class="flex items-center gap-2.5">
                                              
                                                    <a href="profile.html" class="link-1">
                                                       <?= htmlspecialchars($row['team_name']) ?>
                                                    </a>
                                                </div>
                                            </td>
                               
                                            <td class="px-32p py-3">
                                                #<?= $rank++ ?>
                                            </td>
                                            <td class="px-32p py-3">
                                                <?= (int)$row['total'] ?>
                                            </td>
    <td class="px-32p py-3">
                                        <?php foreach ($row['runs'] as $r): ?>
										   <?= (int)$r['p'] ?>
                      <?php if (!empty($r['shot'])): ?>
                        · <a href="<?= htmlspecialchars($r['shot']) ?>" target="_blank" rel="noopener">Shot</a>
                      <?php endif; ?>
					    <?php endforeach; ?>
                  <?php if ($row['count'] < $bestN): ?>
                    <span class="mut">(+<?= ($bestN - $row['count']) ?> Slots)</span>
                  <?php endif; ?>
				  
                                            </td>
                                        </tr>
                      <?php endforeach; ?>
           
     
             
                                    </tbody>
                                </table>
                            </div>
							 <?php endif; ?>
                                        
                     
                                        
                                   
                                        </div>