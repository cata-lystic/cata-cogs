// Search form elements changed
"keyup change".split(" ").forEach(function(e){
  document.getElementById('searchForm').addEventListener(e,search,false);
});

const boxes = document.querySelectorAll('.divToggle');
boxes.forEach(box => {
  box.addEventListener('click', function handleClick(event) {
    console.log('box clicked', event);

    let something = box.getAttribute('data-toggle');
    let boxToggle = document.getElementById(something)
    boxToggle.classList.toggle('fade');

  });
});

/*document.addEventListener('submit', function (event) {
	//event.preventDefault();
} */
/*
headers: {
			'Content-type': 'application/json; charset=UTF-8'
		}*/
function search() {
  let formBox = document.getElementById('searchForm')
  let searchData = new FormData(formBox)

  /*
	fetch('api.php', {
		method: 'POST',
		body: searchData
	}).then(function (response) {
    // The API call was successful!
    return response.text();
  }).then(function (html) {
    // This is the HTML from our response as a text string
    console.log(html);
    document.getElementById('content').innerHTML = html
  }).catch(function (err) {
    // There was an error
    console.warn('Something went wrong.', err);
  });
  */

  // Check which content to show
  var showUser = document.getElementById('showUser').checked
  var showID = document.getElementById('showID').checked

  fetch('api.php', {
    method: 'POST',
    body: searchData
  }).then(function (response) {
	// The API call was successful!
    if (response.ok) {
      return response.json();
    } else {
      return Promise.reject(response);
    }
  }).then(function (data) {

    if (!data['meta']['error']) {
      var output = ''
      for(x in data['results']) {
        let user = (showUser) ? " -"+data['results'][x].user : ''
        let id = (showID) ? "#"+x+": " : ''
        output += "<p class='post'>"+id+""+data['results'][x].msg+""+user+"</p>"
      }
    } else {
      output = data['meta']['error']
    }
    document.getElementById('content').innerHTML = output
  }).catch(function (err) {
    // There was an error
    console.warn('Something went wrong.', err);
  });


}

function ChangeUrl(title, url) {
  if (typeof (history.pushState) != "undefined") {
      var obj = { Title: title, Url: url };
      history.pushState(obj, obj.Title, obj.Url);
  } else {
      console.log("Browser does not support HTML5.");
  }
}