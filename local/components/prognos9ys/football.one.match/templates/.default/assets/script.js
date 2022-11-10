$(document).ready(function () {


    $('.og_goal').on('focusout', function () {
        console.log($(this).val())
        setGoalsAndResult()
    })


    $('.o_btn_rand').on('click', function () {
        console.log('rand')
    })

    $('.o_btn_send_prognosis').on('click', function () {

        validateInput()
    })


})

function setGoalsAndResult() {
    let arGoal = []
    $.each($('.og_goal'), function () {
        arGoal[$(this).data("goal")] = $(this).val()
    })
    console.log('arGoal', arGoal)

    if(arGoal["home"] && arGoal["guest"]){
        console.log('oba tyt')
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