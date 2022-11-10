$(document).ready(function () {


    $('.og_goal').on('focusout, keyup', function () {
        console.log($(this).val())
        setGoalsAndResult()
    })


    $('.o_btn_rand').on('click', function () {
        console.log('rand')
    })

    $('.o_btn_send_prognosis').on('click', function () {

        validateInput()

        sendPrognosis()
        
    })

    $('.o_domination_range').on('click', function () {
        calcRange($(this).val())
    })

    $('.o_dom_h').on('focusout, keyup', function () {
        calcRange($(this).val())
    })
})

function setGoalsAndResult() {
    let arGoal = []
    $.each($('.og_goal'), function () {
        arGoal[$(this).data("goal")] = $(this).val()
    })

    if(arGoal["home"] && arGoal["guest"]){
        setCountGoals(arGoal)
    }
}

function validateInput() {

    data = {type: "match"}

    $.each($('.m_pr_value'), function () {
        console.log($(this).attr('name'))
        data[$(this).attr('name')] = $(this).val()
    })

    console.log('data', data)
}

function setCountGoals(arr) {
    let h = +arr["home"]
    let g = +arr["guest"]

    $('.o_sum_i').val(h+g)

    let diff = h-g
    $('.o_diff_i').val(diff)

    if(diff < 0) $('.or_guest').attr('checked', true)
    if(diff === 0) $('.or_draw').attr('checked', true)
    if(diff > 0) $('.or_home').attr('checked', true)

}

function sendPrognosis() {
    $.ajax({
        url: "/local/components/prognos9ys/football.one.match/templates/.default/ajax/",
        method: "POST", //
        data,
        success: function (result) {
            if (result) {
                result = JSON.parse(result)
            }
        }
    })
}

function calcRange(val) {
    let h = $('.o_dom_h').val()
    let r = $('.o_domination_range')

    if(h !== val){
        $('.o_dom_h').val(val)
        $('.o_dom_g').val(100-val)
    }

    if(r !== val){
        $('.o_domination_range').val(val)
    }
}