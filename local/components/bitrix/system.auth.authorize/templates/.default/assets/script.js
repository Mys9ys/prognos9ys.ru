$(document).ready(function () {

    data = {}

    allValidate()

    $('.naf_input_mail').on('keyup', function () {
        validateEmail($(this).val())
    })

    $('.naf_input_pass').on('keyup', function () {

        validatePass($(this).val())
    })

    $('.naf_btn_auth').on('click', function (e) {
        e.preventDefault();

        data["type"] = "auth"

        myRegisterAjaxRequest()
    })
})

function myRegisterAjaxRequest() {

    $('.fa-spinner').css('visibility', 'visible')
    console.log('data', data)

    $.ajax({
        url: "/local/components/bitrix/system.auth.authorize/templates/.default/ajax/",
        method: "POST", //
        data,
        success: function (result) {
            if (result) {
                result = JSON.parse(result)
                $('.fa-spinner').css('visibility', 'hidden')

                if(result.status === 'ok'){
                    window.location.replace('/')
                } else {
                    $('.naf_form_err_line').html(inputFail(result.mes))
                }
            }
        }
    })
}

function allValidate() {
    arr = []

    $.each($('.naf_input_validate_info'), function () {
        if($(this).find('.bi-check').length === 1){
            let selector = $(this).parent().find('.naf_input')
            data[selector.attr("name")] = selector.val()
            arr.push(1)
        }
    })

    if(arr.length === 2){
        $('.naf_btn_auth').attr("disabled", false)
    } else {
        $('.naf_btn_auth').attr("disabled", true)
    }
}

function validatePass(pass) {
    if(pass && pass.length >5){
        $('.naf_input_pass').parent().find('.naf_input_validate_info').html('').html(inputConfirm())
    } else {
        $('.naf_input_pass').parent().find('.naf_input_validate_info').html('').html(inputFail('!'))
    }
    allValidate()
}

function validateEmail(email) {
    let validate = email.match(
        /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
    )

    if(validate && validate != null) {
        $('.naf_input_mail').parent().find('.naf_input_validate_info').html('').html(inputConfirm())
    } else {
        $('.naf_input_mail').parent().find('.naf_input_validate_info').html('').html(inputFail('!'))
    }
    allValidate()
}

function inputConfirm() {
    return '<div class="text-success"><i class="bi bi-check"></i></div>'
}

function inputFail(mes) {
    return '<div class="text-danger" title="не верный формат">'+mes+'</div>'
}