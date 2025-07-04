/* fmsUnitHelper.js Подсказки: кем выдан паспорт с помощью сервиса DADATA + jquery.suggestions
* version 0.11.001 2025-01-16
*/
(function() {
    $(function() {
        var token = dadataUtl.token, // Token.get(),
            type = "FMS_UNIT",
            $suggestions = $("input.docpodr");

        function formatResult(value, currentValue, suggestion) {
            suggestion.value = suggestion.data.code;
            return suggestion.data.code + " — " + suggestion.data.name;
        }
        // просто подсказки
        $suggestions.suggestions({
            token: token,
            type: type,
            formatResult: formatResult,
            noSuggestionsHint: false,
            onSelect: function(suggestion) {
                elemId = $(this).attr('id');
                docPodr.podrName = elemId;
                docPodr.podrValue = suggestion.value;
                if(typeof(suggestion.data) !='undefined') {
                    let baseNm = elemId.replace("docpodr", "docissued");
                    $('#'+baseNm).val(suggestion.data.name);
                    window.setTimeout(dadataUtl.hideInput, 200);
                    window.setTimeout(docPodr.replacePodr, 200);
                }
            }
        });

    });
})();

docPodr = {
  podrName:'',
  podrValue:'',
  replacePodr: function() {
    $("#"+docPodr.podrName).val(docPodr.podrValue);
  }
}
