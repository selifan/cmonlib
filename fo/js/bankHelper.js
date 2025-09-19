/* bankHelper.js поиск  банка по веденному БИК - dadata + jquery.suggestions
* version 0.10.001 2025-07-04
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
                    var elParts = elemId.split("_");
                    var pref = elParts[0];
                    if (suggestion.data.bic) $("input[name^="+pref+"].bankbic").val(suggestion.data.bic);
                    else $("input[name^="+pref+"].bankbic").val("");
                    if (suggestion.data.correspondent_account) $("input[name^="+pref+"].bankks").val(suggestion.data.correspondent_account);
                    else $("input[name^="+pref+"].bankks").val("");
                }
            }
        });
    });
})();
