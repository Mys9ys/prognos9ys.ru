$(document).ready(function () {
    // <---- работа с десктопной версткой ----> start
    if(screen.width > 799){
        var swiper = new Swiper(".promo_template_swiper", {
            direction: "vertical",
            slidesPerView: 1,
            spaceBetween: 30,
            mousewheel: true,
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
        });

        // var prize_slider = new Swiper(".test", {
        //     slidesPerView: 4,
        //     spaceBetween: 30,
        //     freeMode: true,
        //     navigation: {
        //         nextEl: ".swiper-button-next",
        //         prevEl: ".swiper-button-prev",
        //     },
        //
        // });

        $(".promo_prize_slider").owlCarousel({
            loop: true,
            mouseDrag: true,
            touchDrag: true,
            items:4,
            // center: true,
            // singleItem: true,
            // autoplay: true,
            // autoplayTimeout: 7000,
            autoplayHoverPause: true,
            nav: true,
            navText: ['<i class="fa fa-arrow-left" aria-hidden="true"></i>', '<i class="fa fa-arrow-right" aria-hidden="true"></i>'],
        })

        // обработка нажатия кнопок слайда и подсветки активности меню ----> start
        // добавляем номер слайда
        $('.swiper-pagination').append('<div class="promo_slide_count">1</div>')

        // Обработчик для кнопок управления слайдером в нижне углу
        $('.promo_swiper_nav_left').on('click', function () {//левая
            swiper.slidePrev()
            promoSlideRotation(swiper.activeIndex)
        })
        $('.promo_swiper_nav_right').on('click', function () {//правая
            swiper.slideNext()
            promoSlideRotation(swiper.activeIndex)
        })

        // прокрутка мыши - смена слайдев и активности кнопок меню
        $('.promo_template_swiper').on('mousewheel', function () {
            promoSlideRotation(swiper.activeIndex)
        })
        // обработка нажатия кругляшей пагинации для изменнеия номера слайда
        $('.swiper-pagination-bullet').on('click', function () {
            let sl_index = $(this).attr('aria-label').replaceAll("Go to slide ","")
            promoSlideRotation(sl_index-1, 'bullet')
        })
        //кнопки табы инициируют переход на соответствующий слайд (меню декстоп)
        $('.promo_slide_move').on('click', function () {
            swiper.slideTo($(this).data('index'))
            promoSlideRotation(swiper.activeIndex)
        })

        // паралакс фона на десктопе
        let parallax_promo = document.getElementById('parallax_promo');
        let parallaxInstance = new Parallax(parallax_promo);
        // обработка нажатия кнопок слайда и подсветки активности меню ----> end

        // <---- работа с десктопной версткой ----> end
    } else {
        // <---- работа с мобильной версткой ----> start

        // вырезаем соцсети
        const social_btn_box = $('.promo_slider_social_btn').detach()

        // вырезаем кнопку регистрации
        const reg_btn = $('.promo_template_reg_btn').detach()

        // вырезаем кнопку обратная связь
        let promo_ask_a_question_btn = $('.promo_load_check_btn_faq').detach()

        // перекидываем доп элементы первого блока
        $('.promo_slide_body_main_box').append(social_btn_box.clone())
        $('.promo_slide_body_main_box').append(reg_btn.clone())
        $('.promo_slide_main_box').append($('.promo_first_slider_hidden').detach())


        // кнопка обратной связи в блок вопрос ответ
        $('.promo_slide_box_faq').append(promo_ask_a_question_btn.clone())
        $('.promo_slide_box_faq').append(reg_btn.clone())

        // закидываем кнопки соцсетей
        $('.promo_template_title_right_block').append(social_btn_box)

        // в меню обратную связь
        $('.promo_template_title_right_block').append(promo_ask_a_question_btn.text('Задать вопрос'))

        // все невидимые блоки делаем видимыми
        $('.elem_slide_vis').addClass('elem_visible')

        // замена стрелок в блоке с шагами для участия
        $('.promo_slide_step_spacer_img').attr('src', $('.promo_slide_step_spacer_img').data('mobile'))


        // подмена фона
        $('.para_promo_background').attr('src', $('.para_promo_background').data('mobile'))

        // закидываем кнопку меню в блок
        $('.promo_template_wrapper').prepend('<i class="fa fa-bars promo_mobile_menu" aria-hidden="true"></i>')

        // закидываем кнопку регистрации в всплывающее меню
        $('.promo_template_title_right_block').prepend(reg_btn)

        // закидываем кнопку закрытия меню
        $('.promo_template_title_right_block').prepend('<i class="fa fa-times promo_mobile_menu_close" aria-hidden="true"></i>')


        $('.promo_mobile_menu').on('click', function () {
            $('.promo_template_title_right_block').show()
        })

        $('.promo_mobile_menu_close').on('click', function () {
            $('.promo_template_title_right_block').hide()
        })

        // якорное меню для мобилки
        $('.promo_slide_move').on('click', function(e){
            e.preventDefault();
            $('.promo_template_title_right_block').hide()
            var t = 1000;
            var d = '#promo_block_' + $(this).data('index');
            $('html,body').stop().animate({ scrollTop: $(d).offset().top }, t);
        });

        $(window).scroll(function(){
            if ($(window).scrollTop() > 200) {
                $('.promo_mobile_menu').addClass('promo_mobile_menu_fixed');
            }
            else {
                $('.promo_mobile_menu').removeClass('promo_mobile_menu_fixed');
            }
        });

        // <---- работа с мобильной версткой ----> end
    }

    $('.promo_phone_mask').on('click', function () {
        setCursorPosition(3)
    }).mask("+7(999)-999-99-99", {autoclear: false});// маска для номера телефона

    $('.promo_order_mask').on('click', function () {
        setCursorPosition(1)
    }).mask("99999999999?9", {});//  маска для номера заказа

    // поиск по номеру заказа
    let winners_list = $('.promo_winner_item').clone()
    $('.promo_winner_search').on('keyup change focusout', function () {
        $('.promo_winner_item').detach()
        let search_val = $(this).val().replaceAll("_","");

        $.each(winners_list, function (index, value) {
            if($(this).find('.promo_item_order').text().includes(search_val)){
                $('.promo_winners_table_body').append($(this))
            }
        })

        if(!search_val) {
            $('.promo_winners_table_body').append(winners_list)
        }
    })

    // список вопрос-ответ
    $(".faq_list>li .name").on('click', function (e) {
        e.preventDefault()
        if ($(".faq_list>.open").length > 0) {
            if ($(this).parent().hasClass("open")) {
                $(this).parent().removeClass("open")
            } else {
                $(".faq_list>.open").removeClass("open")
                $(this).parent().addClass("open")
            }
        } else {
            $(this).parent().addClass("open")
        }
    })

    // самописный модал
    $('.promo_load_modal_btn').on('click', function () { // лoвим клик пo ссылки с id="go"
        $('#overlay_promo_modal').fadeIn(400, // снaчaлa плaвнo пoкaзывaем темную пoдлoжку
            function () { // пoсле выпoлнения предъидущей aнимaции
                $('.promo_modal_form_registration')
                    .css('display', 'block') // убирaем у мoдaльнoгo oкнa display: none;
                    .animate({opacity: 1}, 200); // плaвнo прибaвляем прoзрaчнoсть oднoвременнo сo съезжaнием вниз
            });
    });
    $('.promo_load_callback_btn').on('click', function () { // лoвим клик пo ссылки с id="go"
        $('#overlay_promo_modal').fadeIn(400, // снaчaлa плaвнo пoкaзывaем темную пoдлoжку
            function () { // пoсле выпoлнения предъидущей aнимaции
                $('.promo_modal_form_callback')
                    .css('display', 'block') // убирaем у мoдaльнoгo oкнa display: none;
                    .animate({opacity: 1}, 200); // плaвнo прибaвляем прoзрaчнoсть oднoвременнo сo съезжaнием вниз
            });
    });
    /* Зaкрытие мoдaльнoгo oкнa, тут делaем тo же сaмoе нo в oбрaтнoм пoрядке */
    $('.promo_modal_close, #overlay_promo_modal').on('click', function () { // лoвим клик пo крестику или пoдлoжке
        $('.promo_modal_form_template').animate({opacity: 0}, 200,  // плaвнo меняем прoзрaчнoсть нa 0 и oднoвременнo двигaем oкнo вверх
            function () { // пoсле aнимaции
                $(this).css('display', 'none'); // делaем ему display: none;
                $('#overlay_promo_modal').fadeOut(400); // скрывaем пoдлoжку
            });
    });





    //$('input[name="phone"]').mask("+7 - 999 - 999 - 99 - 99", {placeholder: "_"});

    $("body").on("click", '.promo_label_politics', function(event) {
        if($(this).find('input[type="checkbox"]').prop('checked')) {
            $(this).find('input[type="checkbox"]').prop('checked', false);
            $(this).find('.politics_circle').html('');
        } else {
            $(this).find('input[type="checkbox"]').prop('checked', true);
            $(this).find('.politics_circle').html('<span class="politics_mark"></span>');
        }
    });

    $("body").on("click", '#form_registration .promo_form_reg_btn', function(event) {
        event.preventDefault();

        var preloader = $(this).closest('.promo_modal_form_template').find('.promo_modal_form_loading'),
            data = $('#form_registration').serializeArray(); // convert form to array

        data.push({name: "type_form", value: 'registration'});
        preloader.show();

        $.ajax({
            url: $('#form_registration').attr('action'),
            type: 'POST',
            data: $.param(data),
            dataType: 'html',
            success: function (data) {
                $('#form_registration').html(data);
                preloader.hide();

                //$('input[name="phone"]').mask("+7 - 999 - 999 - 99 - 99", {placeholder: "_"});
                // $('.promo_order_mask').mask("999999999999");// маска для номера заказа
                // $('.promo_phone_mask').mask("+7(999)-999-99-99");// маска для номера телефона
            }
        });
    });

    $("body").on("click", '#form_feedback .promo_form_reg_btn', function(event) {
        event.preventDefault();

        var preloader = $(this).closest('.promo_modal_form_template').find('.promo_modal_form_loading'),
            data = $('#form_feedback').serializeArray(); // convert form to array

        data.push({name: "type_form", value: 'feedback'});
        preloader.show();

        $.ajax({
            url: $('#form_feedback').attr('action'),
            type: 'POST',
            data: $.param(data),
            dataType: 'html',
            success: function (data) {
                $('#form_feedback').html(data);
                preloader.hide();

                //$('input[name="phone"]').mask("+7 - 999 - 999 - 99 - 99", {placeholder: "_"});
                $('.promo_phone_mask').mask("+7(999)-999-99-99");// маска для номера телефона
            }
        });
    });

    /*$("body").on("keyup", '#form_search_winner .promo_winner_search', function(event) {
        $.ajax({
            url: $('#form_search_winner').attr('action'),
            type: 'POST',
            data: {'val': $('.promo_winner_search').val(), 'type_form': 'winner_search'},
            dataType: 'html',
            success: function (data) {
                $('#table_winners').html(data);
            }
        });
    });*/
})



function promoSlideRotation(index){
    clearLinkPromo()
    promoInvisibleSlideElem()

    // обработка перехода слайдов
    $('.elem_slide_vis_'+index).addClass('elem_visible')
    $('.promo_slide_count').text(index+1)
    if(index-1 > -1) $('.promo_slide_move').eq(index-1).addClass('active')
}

function clearLinkPromo() {
    $.each($('.promo_slide_move'), function () {
        $(this).removeClass('active')
    })
}

function promoInvisibleSlideElem(){
    $.each($('.elem_slide_vis'), function () {
        $(this).removeClass('elem_visible')
    })
}

function setCursorPosition(pos) {
    if ($(this).get(0).setSelectionRange) {
        $(this).get(0).setSelectionRange(pos, pos);
    } else if ($(this).get(0).createTextRange) {
        var range = $(this).get(0).createTextRange();
        range.collapse(true);
        range.moveEnd('character', pos);
        range.moveStart('character', pos);
        range.select();
    }
}