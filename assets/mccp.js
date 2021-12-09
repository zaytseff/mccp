;(function($) {
  $.urlParam = function(name){
    var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (!results) { return 0; }
    return results[1] || 0;
  };
  async function getStat() {
    return $.ajax({
      url: "/?wc-api=mccp_check&key=" + key + "&order_id=" + order_id,
      dataType: 'json',
    });
  };
  var key = $.urlParam('key');
  var order_id = $.urlParam('order');

  function showCopyIcon (el) {
    $(el).addClass('copied');
    setTimeout(function() {
      $(el).removeClass('copied')
    },500);
  }
  function copy2clipboard(el) {
    // if(navigator.clipboard ) {
    if(navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(el.innerHTML)
        .then(showCopyIcon(el))
        .catch();
    } else {
      let textArea = document.createElement("textarea");
      textArea.value = el.innerHTML;
      textArea.style.position = "fixed";
      textArea.style.left = "-999999px";
      textArea.style.top = "-999999px";
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      return new Promise((res, rej) => {
          document.execCommand('copy') ? res(el) : rej();
          textArea.remove();
      });
    }
  }

  $(document).ready(async function() {
    $(".copy2clipboard").on('click', function(){
      copy2clipboard(this).then((el) => showCopyIcon(el)).catch(() => console.log('error copy'));
      // copy2clipboard(this);
    });
    const stat = await getStat();
    let current = 0;
    async function process() {
      $(".loader-wrapper").addClass("loader-show");
      current = await getStat();
      if (current.status > stat.status) {
        document.location.reload();
      }
      $(".loader-wrapper").removeClass("loader-show");
    };

    if ($('#mccp-payment').length > 0) {
      var handler = setInterval(process, 1e4)
    }
  });
})(jQuery);