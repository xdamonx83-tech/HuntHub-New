
 
 <div class="3xl:col-span-7 xl:col-span-6 col-span-10">
                                    <div>
                                        <div
                                            class="flex-y flex-wrap gap-x-32p bg-b-neutral-3 px-32p py-24p rounded-12 mb-32p">
                               
								     <div>
                                                    <span class="text-base text-w-neutral-4 mb-1"><?php echo $L['format']; ?></span>
                                                    <span class="text-xl-medium text-w-neutral-1">
                                                        <?= htmlspecialchars((string)$t['format']) ?>
                                                    </span>
                                                </div>
                                            <div class="flex-y gap-32p">
                                                <div>
                                                    <span class="text-base text-w-neutral-4 mb-1"><?php echo $L['status']; ?></span>
                                                    <span class="text-xl-medium text-w-neutral-1">
                                                        <?= htmlspecialchars((string)$t['status']) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-base text-w-neutral-4 mb-1"><?php echo $L['teams']; ?>
                                                      </span>
                                                    <span class="text-xl-medium text-w-neutral-1">
                                                     <?= (int)$t['team_size'] ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-b-neutral-3 py-40p px-32p rounded-12">
                                            <h3 class="heading-3 text-w-neutral-1 mb-3">
                                                <?php echo $L['event_info']; ?>
                                            </h3>
                                            <p class="text-base text-w-neutral-4 mb-24p">
                                 <?= safe_html((string)($t['description'] ?? '')) ?>
								   
								        
                                            </p>
 
                                        
                     
                                        
                                   
                                        </div>
                                    </div>
                                </div>