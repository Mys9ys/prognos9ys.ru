$(document).ready(function () {

    $('.pw_pb_radio_btn_otime').on('click', function () {
        $('.pw_pb_otime_value').val($(this).data("value"))
    })

    $('.pw_pb_radio_btn_spenalty').on('click', function () {
        $('.pw_pb_spenalty_value').val($(this).data("value"))
    })

    $('.og_goal').on('focusout, keyup, change', function () {
        setGoalsAndResult()
    })

    $('.o_admin_calc').on('click', function () {

        let set = {
            type: "set_result",
            id:  $(this).data("id")
        }

        console.log('set', set)
        $.ajax({
            url: "/local/components/prognos9ys/football.one.match/templates/.default/ajax/set/",
            method: "POST", //
            data: set,
            success: function (result) {

            }
        })
    })


    $('.o_btn_rand').on('click', function () {
        // console.log('rand')
    })

    $('.c_yellow').on('click, change', function () {
        $('.c_yellow').val($(this).val())
    })

    $('.c_red').on('click, change', function () {
        $('.c_red').val($(this).val())
    })

    $('.o_corner_i').on('click, change', function () {
        $('.o_corner_i').val($(this).val())
    })

    $('.o_penalty_i').on('click, change', function () {
        $('.o_penalty_i').val($(this).val())
    })

    $('.o_btn_send_prognosis').on('click', function () {

        validateInput()

        sendPrognosis()
        
    })

    $('.set_match_result').on('click', function () {
        validateInput()
        console.log('data', data)
        $.ajax({
            url: "/local/components/prognos9ys/football.one.match/templates/.default/ajax/set_result/",
            method: "POST", //
            data,
            success: function (result) {

            }
        })
    })

    $('.o_domination_range, .pw_domination_range').on('click, change', function () {
        calcRange($(this).val())
    })

    $('.dom_home').on('focusout, keyup, change', function () {
        calcRange($(this).val())
    })

    $('.pw_goals_popular_score, .pw_goals_btn, .pw_dom_btn, .pw_card_btn, .pw_corner_btn, .pw_penalty_btn').on('click', function () {
        console.log('this.val()', $(this).text(), 'this.cell', $(this).data("cell"))

        changeValueMatchInput($(this).text(), $(this).data("cell"))
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
        $('.or_radio').attr('checked', false)
        $('.or_guest').attr('checked', true)
        $('.or_radio_res').val('п2')
    }
    if(diff === 0) {
        $('.or_radio').attr('checked', false)
        $('.or_draw').attr('checked', true)
        $('.or_radio_res').val('н')
    }
    if(diff > 0) {
        $('.or_radio').attr('checked', false)
        $('.or_home').attr('checked', true)
        $('.or_radio_res').val('п1')
    }


}

function sendPrognosis() {
    $('.prog_send_modal').modal("show")
    $.ajax({
        url: "/local/components/prognos9ys/football.one.match/templates/.default/ajax/",
        method: "POST", //
        data,
        success: function (result) {
            if (result) {
                result = JSON.parse(result)
                if(result["status"]==="ok"){
                    $('.modal-body').html('').html('<div class="text-success"><i class="fa fa-check-square-o" aria-hidden="true"></i></div><span class="text-success">'+result["mes"]+'</span>')
                }
                if(result["status"]==="err"){
                    $('.modal-body').html('').html('<span class="text-danger">'+result["mes"]+'</span>')
                }
            }
        }
    })
}

function calcRange(val) {
    let h = +$('.dom_home').val()
    let r = +$('.o_domination_range')

    if(h !== val){
        $('.dom_home').val(val)
        $('.dom_guest').val(100-val)
    }

    if(r !== val){
        $('.o_domination_range, .pw_domination_range').val(val)
    }
}

function changeValueMatchInput(val, cell){

    switch (cell) {
        case "goal":
            arr = val.split('-')
            $('.'+cell +'_home').val(+arr[0])
            $('.'+cell +'_guest').val(+arr[1])
            setGoalsAndResult()
            break;
        case "goal_home":
        case "goal_guest":
            val = +val
            $('.'+cell).val(+$('.'+cell).val()+val)
            if(val === 0) $('.'+cell).val(0)
            setGoalsAndResult()
            break;
        case "dom_home":
        case "dom_guest":
            arr = {
                dom_home: "dom_guest",
                dom_guest: "dom_home"
            }
            val = +val
            $('.' + cell).val(+$('.' + cell).val()+val)
            $('.' + arr[cell]).val(+$('.' + arr[cell]).val()-val)

            if(val === 50) {
                $('.' + cell).val(val)
                $('.' + arr[cell]).val(val)
                $('.domination_range').val(val)
            } else {
                if(cell === "dom_home") $('.domination_range').val(+$('.domination_range').val()+val)
                if(cell === "dom_guest") $('.domination_range').val(+$('.domination_range').val()-val)
            }
            setGoalsAndResult()
            break;
        case "c_red":
        case "c_yellow":
        case "o_corner_i":
            val = +val
            $('.'+cell).val(+$('.'+cell).val()+val)
            if(val === 0) $('.'+cell).val(0)
            setGoalsAndResult()
        break;
        case "o_penalty_i":
            val = +val
            let old = +$('.'+cell).val()
            old += val
            console.log('old', old)
            if(old >9) old = 9
            $('.'+cell).val(old)
            if(val === 0) $('.'+cell).val(0)
            setGoalsAndResult()
            break;
    }
}