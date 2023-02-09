$(document).ready(function () {
    console.log('dfsdfs')
    
    $('.event_get_btn').on('click', function () {
        data = {
            id: $(this).data('user'),
            event: $(this).data('event')
        }
        $.ajax({
            url: "/local/components/prognos9ys/events.select/templates/.default/ajax/",
            method: "POST", //
            data,
            success: function (result) {
                location.reload ()
            }
        })
    })
})