$(document).ready(function () {

    $('.naf_btn').on('click', function (e) {
        e.preventDefault();


        let data = $('form').serializeArray()
        // data = data.map((el, i) =>{ el.name => el.value })
        data["type"] = "reg"
        console.log("data", data)
        // myRegisterAjaxRequest($('form').serialize())
    })


})

function myRegisterAjaxRequest(data) {

    $.ajax({
        url: "/local/components/bitrix/system.auth.registration/ajax/",
        method: "POST", //
        data,
        success: function (result) {
            if (result) {
                result = JSON.parse(result)

            }
        }
    })
}