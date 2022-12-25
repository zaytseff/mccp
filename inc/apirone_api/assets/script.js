;jQuery(document).ready((function($) {
    var countdown = $('#countdown').val();
    var current = $('#current').val();
    var url = $('#statusUrl').attr('href');
    var ct = $('#check_timeout').length === 1 ? $('#check_timeout').val() : 10;
    var processId;
    var stopWatchId;
    

    async function getStat() {
        return await $.ajax({url: url, dataType: 'text'});
    };
  
    function showCopyIcon (el) {
        $(el).addClass('copied');
        setTimeout(function() {
            $(el).removeClass('copied')
        },500);
    }
    function copy2clipboard(el) {
        if(navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(el.innerHTML).then(showCopyIcon(el)).catch();
        }
        else {
            let textArea = document.createElement("textarea");
            textArea.value = el.innerHTML;
            textArea.style.position = "fixed";textArea.style.left = "-999999px";textArea.style.top = "-999999px";
            document.body.appendChild(textArea); textArea.focus(); textArea.select();
            return new Promise((res, rej) => {document.execCommand('copy') ? res(el) : rej(); textArea.remove();});
        }
    }

    function stopWatch() {
        let timer = $('#stopwatch');
        if (timer.length > 0 && countdown !== 'undefined') {
            $('#stopwatch').html(s2t(countdown));
            if (countdown <= 0) {
                clearInterval(stopWatchId);
                $('.mccp-status-desc').html('Updating status...');
                $('#stopwatch').html('');
                return;
            }
            countdown = countdown - 1;
        }
    }

    function s2t(s) {
        return s <=0 ? '' : (s>3600) ? new Date(s*1000).toISOString().slice(11,19) : new Date(s*1000).toISOString().slice(14,19);
    }

    async function init() {
        if ($('.mccp-wrapper').length === 0 ) { return; }
        $('.date-gmt').each(function(){
            let gmt = new Date(this.innerHTML);
            let local = gmt.toLocaleString();
            this.innerHTML = local.includes('Invalid') ? this.innerHTML+" GMT" : local;
        });
        $(".copy2clipboard").on('click', function(){
            copy2clipboard(this).then((el) => showCopyIcon(el)).catch(() => console.log('error copy'));
        });
        var stat = await getStat();

        async function refresh() {
            if (current === 0) { clearInterval(processId); return; }
            $(".loader-wrapper").addClass("loader-show");
            current = await getStat();
            $(".loader-wrapper").removeClass("loader-show");
            if (current !== stat) { document.location.reload();}
        };
        if (countdown > 0) { stopwatchId = setInterval(stopWatch, 1e3); }
        if (current > 0) { processId = setInterval(refresh, ct*1000); }
    };
    init();
}));
