<?php // erwartet $APP_BASE ?>
<div class="hh-search" id="hh-search" role="combobox" aria-expanded="false" aria-owns="hh-search-list">
  <form class="hh-search-form" role="search" onsubmit="return window.hhHeaderSearch && window.hhHeaderSearch.submit(event);">
    <input
      id="hh-search-input"
      class="hh-search-input"
      type="search"
      name="q"
	  style="width:200px;"
      autocomplete="off"
      spellcheck="false"
      placeholder="Mitglieder/Foren suchen…"
      aria-autocomplete="list"
      aria-controls="hh-search-list"
      aria-activedescendant=""
	  data-members-autocomplete
    />

  </form>
  <div class="hh-search-dropdown" id="hh-search-dropdown" hidden>
    <div class="hh-search-section" id="hh-search-users" hidden>
      <div class="hh-search-section-title">Mitglieder</div>
      <ul id="hh-search-list" class="hh-search-list" role="listbox"></ul>
    </div>
  </div>
</div>