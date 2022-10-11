$(document).ready(function () {
    // карусель большая
    if (screen.width > 780) {
        var swiper = new Swiper(".action_slider_swiper", {
            slidesPerView: 4,
            spaceBetween: 10,
            freeMode: true,
            autoplay: {
                delay: 5000,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
        })
    }




        // Mys9ys lazyload для слаедера с акциями 30.08.21
        if (screen.width < 780) {
            $.each($('.action_img_class'), function () {
                $(this).attr('src', $(this).data('mob'))
            })
        } else {
            $.each($('.action_img_class'), function () {
                $(this).attr('src', $(this).data('desc'))
            })
        }

})
