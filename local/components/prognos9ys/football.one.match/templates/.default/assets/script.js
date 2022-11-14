$(document).ready(function () {


    $('.og_goal').on('focusout, keyup, change', function () {
        setGoalsAndResult()
    })


    $('.o_btn_rand').on('click', function () {
        // console.log('rand')
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

    $('.pw_goals_popular_score, .pw_goals_btn').on('click', function () {
        console.log('this.val()', $(this).text(), 'this.cell', $(this).data("cell"), 'this.type', $(this).data("type"))

        changeValueMatchInput($(this).text(), $(this).data("cell"), $(this).data("type"))
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
        data[$(this).attr('name')] = $(this).val()
    })

}

function setCountGoals(arr) {
    let h = +arr["home"]
    let g = +arr["guest"]

    $('.o_sum_i').val(h+g)

    let diff = h-g
    $('.o_diff_i').val(diff)

    if(diff < 0) {
        $('.or_guest').attr('checked', true)
        $('.or_radio_res').val('п1')
    }
    if(diff === 0) {
        $('.or_draw').attr('checked', true)
        $('.or_radio_res').val('н')
    }
    if(diff > 0) {
        $('.or_home').attr('checked', true)
        $('.or_radio_res').val('п2')
    }


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

function changeValueMatchInput(val, cell, type, action=''){

    switch (type) {
        case "double":
            arr = val.split('-')
            console.log('arr',arr)
            $('.'+cell +'_home').val(+arr[0])
            $('.'+cell +'_guest').val(+arr[1])
            setGoalsAndResult()
            break;
        case "one":
            val = +val
            $('.'+cell).val(+$('.'+cell).val()+val)
            if(val === 0) $('.'+cell).val(0)
            setGoalsAndResult()
            break;
    }
}