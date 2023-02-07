$(document).ready(function () {
    $('.kvn_span_show').on('mouseenter', function () {
        mys9ysEnter($(this))
        $(this).on('mouseout', function () {
            mys9ysLeave($(this))
        })
    })

    function mys9ysEnter($this) {
        $('.kvn_span_hide').hide()
        $this.parent().find('.kvn_span_hide').show()
        $this.hide()
    }

    function mys9ysLeave($this) {
        $this.hide()
        $this.parent().find('.kvn_span_show').show()
    }
})
