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

function search() {
  let formBox = document.getElementById('searchForm')
	fetch('api.php', {
		method: 'POST',
		body: JSON.stringify(Object.fromEntries(new FormData(formBox))),
		headers: {
			'Content-type': 'application/json; charset=UTF-8'
		}
	}).then(function (response) {
		if (response.ok) {
			//return response.json();
      return response
		}
		return Promise.reject(response);
	}).then(function (data) {
		console.log(data);
	}).catch(function (error) {
		console.warn(error);
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