$(document).ready(function () {
    $('.header_btn_menu').on('click', function () {
        $('.dropdown-menu').toggle()
    })

    $('.menu_events_btn').on('click', function () {
        $('.'+$(this).data('submenu')).toggle()
        $(this).find('.bi-caret').toggleClass('bi-caret-down-fill').toggleClass('bi-caret-right-fill')
    })
})