(function(){
  try{
    window.CMS = {
      base: document.querySelector('meta[name="app-base"]')?.content || '',
      csrf: document.querySelector('meta[name="csrf"]')?.content || '',
      meId: parseInt(document.querySelector('meta[name="me-id"]')?.content || '0', 10) || 0
    };
  }catch(e){ window.CMS = { base:'', csrf:'', meId:0 }; }
})();