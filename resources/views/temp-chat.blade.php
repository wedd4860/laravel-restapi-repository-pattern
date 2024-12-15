<!DOCTYPE html>

<head>
  <title>Pusher Test</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="./css/app.css" rel="stylesheet">
</head>

<body>
  <div class="jumbotron text-center mt-8">
    <h1>chat app</h1>
  </div>
  <div class="comntainer m-8">
    <div class="row m-8 p-5">
      <dov class="col-xs-6">
        <div class="card">
          <div class="card-body">
            <div class="mb-3" id="messageOutput"></div>
            <hr>
            <form id="chatForm">
              <div class="form-group mb-3">
                <input type="text" class="form-control" id="message" placeholder="Message">
              </div>
              <button type="submit" class="btn btn-success">Send</button>
            </form>
          </div>
        </div>
      </dov>
    </div>
  </div>
  <script src="./js/app.js"></script>
</body>