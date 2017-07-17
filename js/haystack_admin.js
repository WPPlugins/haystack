(function($) {
    var Check_Stats = function(data) {
        for (var i in data) {
            this[i] = data[i];
        }
        if ($(this.row).length > 0) {
            this.init();
        }
    };
    Check_Stats.prototype = {
        progress: 1,
        delay: 2000,
        stats: [],
        init: function() {
            var Obj = this;
            Obj.call();
        },
        set_progress: function(callback) {
            callback = typeof callback === 'undefined' ? function() {} : callback;
            var Obj = this;
            $(Obj.status_num).text(Obj.progress + '%' + (Obj.progress != 100 ? ' (' + Obj.stats.num + ' of ' + Obj.stats.total + ')' : ''));
            Obj.set_progress_bar(callback);
        },
        set_progress_bar: function(callback) {
            var Obj = this;
            var delay = (Obj.progress == 100 ? 200 : Obj.delay);
            $(Obj.status_bar).finish().animate({
                width: Obj.progress + '%'
            },delay,function() {
                callback();
            });
        },
        fail: function() {
            var Obj = this;
            var t = setTimeout(function() {
                Obj.call();
            },Obj.delay);
        },
        success: function() {
            var Obj = this;
            Obj.progress = 100;
            var complete = function() {
                var t = setTimeout(function() {
                    $(Obj.row).fadeOut(200);
                },1000);
            };
            Obj.set_progress(complete);
        },
        call: function() {
            var Obj = this;
            $.ajax({
                url: ajax_data.status_url,
                data: {
                    stats: true,
                },
                success: function(res) {
                    var stats = JSON.parse(res);
                    for (var i in stats) {
                        Obj.stats[i] = !stats[i] ? 0 : stats[i];
                    }
                    Obj.progress = Math.round( (100 * Obj.stats.num / Obj.stats.total) * 10) / 10;
                    Obj.set_progress();

                    if (Obj.stats.total != 0) {
                        Obj.fail();
                    }
                    else {
                        Obj.success();
                    }
                }
            });
        }
    };


    $(document).ready(function() {
        var Stats = new Check_Stats({
            row: '.haystack_stats_row',
            status_bar: '.haystack_status_inner',
            status_num: '.haystack_status_num',
        });

        //Suggest menu
        if ($('input[name="suggest_menu"]:checked').val() != 'default') {
            $('#quick_links_text_row').hide();
        }
        $('input[name="suggest_menu"]').click(function(e) {
            if ($(this).val() == 'default') {
                $('#quick_links_text_row').slideDown();
            }
            else {
                $('#quick_links_text_row').slideUp();
            }
        });
    });
})(jQuery);