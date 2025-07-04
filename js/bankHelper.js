/* bankHelper.js поиск  банка по веденному БИК - dadata + jquery.suggestions
* version 0.01.001 2025-07-04
*/
(function() {
    $(function() {
        // Token.init();
        // console.log("suggestions init...");
        var token = dadataUtl.token, // Token.get(),
            type = "BANK",
            $suggestions = $("input.bankname");

        // просто подсказки
        var suggestionsInstance = $suggestions.suggestions({
            token: token,
            type: type,
            hint: false,
            addon: "clear",
            noSuggestionsHint: false,
            onInvalidateSelection: function() {
                elemId = $(this).attr('name');
                // console.log("ON INVALIDATE SELECTION");
                // $("input.dd").val("");
            },
            onSelect: function(suggestion) {
                elemId = $(this).attr('name');
                // console.log(elemId);
                if(typeof(suggestion.data) !='undefined') {
                    // console.log('selected from input: ', elemId);
                    // console.log('DADATA FOUND data:', suggestion.data); // debug view
                    var elParts = elemId.split("_"); // ddd,insr,reg,[1...N]
                    var fact = (elParts[2]==='fact') ? 'f':'';
                    var subNo = (typeof(elParts[3])!=='undefined') ? elParts[3] : '';
                    var baseNm = '#' + elParts[1] + fact;
                    var foundRegionId = false;
                    /*
                    if (suggestion.data.house_kladr_id) $("#dd_kladr").val(suggestion.data.house_kladr_id);
                    else if (suggestion.data.street_kladr_id) $("#dd_kladr").val(suggestion.data.street_kladr_id);
                    else if (suggestion.data.street_kladr_id) $("#dd_kladr").val(suggestion.data.street_kladr_id);
                    else $("#dd_kladr").val("");
                    */
                    if (suggestion.data.bic) $("input[name^="+elParts[0]+"].bankbic").val(suggestion.data.bic);
                    else $("input[name^="+elParts[0]+"].bankbic").val("");
                    if (suggestion.data.correspondent_account) $("input[name^="+elParts[0]+"].bankks").val(suggestion.data.correspondent_account);
                    else $("input[name^="+elParts[0]+"].bankks").val("");

                    dadataUtl.hideId = elemId;
                    window.setTimeout(dadataUtl.hideInput, 200);
                }
                // else console.log('DADATA FAIL ', suggestion);


            }
        });
    });
})();
