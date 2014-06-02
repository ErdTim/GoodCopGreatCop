(function($) {
    $(document).ready(function() {
        $('#gcgc_social_widget form').submit(function(e) {
            var self = this;
            var data = $(this).serializeArray();
            var str = {
                big : ' -- ',
                small : ' | '
            };
            var concat = '';
            var name;

            for (var i = 0; i < data.length; i++) {
                if ($.trim(data[i].value) === '') {
                    alert("All 'Social Fields' must be filled in.");
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                name = data[i]['name'].split('_');

                if (name[0] === 'gcgc') {
                    if (name[1] === 'title') {
                        concat = concat + str.big + $.trim(data[i].value);
                    } else {
                        concat = concat + str.small + $.trim(data[i].value);
                    }
                };
            };

            data.push({
                name: 'content',
                value: $.trim(concat)
            });

            data.push({
                name: 'action',
                value: $(this).attr('action')
            });

            data.push({
                name: 'post_id',
                value: GCGC_SOCIAL.post_id
            });

            $.ajax({
                type : "POST",
                dataType : "json",
                url : GCGC_SOCIAL.admin_ajax,
                data : data,
                success: function(response) {
                    // bounce the bg color
                    var $postbox = $(self).parents('.postbox');
                    var bgColor = $postbox.css('backgroundColor');
                    var respColor = (response.type == 'success') ? '#ffff33' : '#ffaaaa';

                    $postbox.css({'background' : respColor})
                        .animate({'backgroundColor': bgColor}, 650);
                }
            });

            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    });
})(jQuery);