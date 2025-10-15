(function($){
  $(function(){
    var $btn = $('#sora_dlsite_fetch');
    var $url = $('#sora_dlsite_url');
    var $res = $('#sora_dlsite_result');
    var $ovr = $('#sora_dlsite_overwrite');

    if (!$btn.length) return;

    $btn.on('click', function(){
      var u = ($url.val() || '').trim();
      if (!u) {
        $res.html('<div class="notice notice-error"><p>Please enter a URL.</p></div>');
        return;
      }
      var labels = (window.SoraDLsite && SoraDLsite.labels) ? SoraDLsite.labels : {};
      $res.html('<div class="notice notice-info"><p>'+ (labels.fetching || 'Fetching…') +'</p></div>');

      $.ajax({
        url: (window.SoraDLsite ? SoraDLsite.ajax : ajaxurl),
        type: 'POST',
        dataType: 'json',
        data: {
          action: (window.SoraDLsite ? SoraDLsite.action : 'sora_fetch_dlsite'),
          nonce:  (window.SoraDLsite ? SoraDLsite.nonce  : ''),
          post_id: $('#post_ID').val(),
          url: u,
          overwrite: $ovr.is(':checked') ? 1 : 0
        }
      }).done(function(resp){
        if (resp && resp.success) {
          $res.html('<div class="notice notice-success"><p>'+ (labels.done || 'Filled successfully.') +'</p>'+ (resp.data.summary || '') +'</div>');
          $url.val(u);
        } else {
          var msg = (resp && resp.data) ? resp.data : (labels.error || 'An error occurred.');
          $res.html('<div class="notice notice-error"><p>'+ msg +'</p></div>');
        }
      }).fail(function(){
        $res.html('<div class="notice notice-error"><p>'+ (labels.error || 'An error occurred.') +'</p></div>');
      });
    });
  });
})(jQuery);
