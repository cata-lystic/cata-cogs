$(document).ready(function() {
  $("#searchSubmit").hide() // hide search button
})

// Make jQuery :contains case-insensitive
$.expr[":"].contains = $.expr.createPseudo(function(arg) {
  return function( elem ) {
      return $(elem).text().toUpperCase().indexOf(arg.toUpperCase()) >= 0;
  };
});

// Search form elements changed
$(document).on("click", ".divToggle", function(e) {
  var clicked = $(this).attr("data-toggle")
  $(clicked).slideToggle()
  
})

function search() {
  let searchQuery = $("#searchbox").val()
  let limit = $("#searchLimit").val()
  let quotes = $("#searchQuotes").val()
  let shuffle = ($("#searchShuffle").prop("checked")) ? 1 : 0 
  let showID = ($("#searchShowID").prop("checked")) ? 1 : 0
  let platform = "api"
  if (searchQuery == "") searchQuery = "list"
  $.ajax({
    type: "GET",
    url: "index.php?q="+searchQuery+"&limit="+limit+"&shuffle="+shuffle+"&showID="+showID+"&quotes="+quotes+"&platform="+platform+"&breaks=1",
    cache: false
  }).done(function (data, textStatus, errorThrown) {
    $("#content").html(data)
    // Change the URL for easy copy/pasting
    ChangeUrl("test", "?q="+searchQuery+"&limit="+limit+"&shuffle="+shuffle+"&showID="+showID+"&quotes="+quotes+"&breaks=1")
  })
}

function ChangeUrl(title, url) {
  if (typeof (history.pushState) != "undefined") {
      var obj = { Title: title, Url: url };
      history.pushState(obj, obj.Title, obj.Url);
  } else {
      console.log("Browser does not support HTML5.");
  }
}