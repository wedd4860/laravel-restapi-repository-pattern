<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin-allow-popups">
    <title>oauth</title>
</head>

<body>
    <script>
        const userInfo = {
            'member_id': "{{$member_id}}",
            'email': "{{$email}}",
            'nickname': "{{$nickname}}",
            'service': "{{$service}}",
            'device': "{{$device}}",
            'token': "{{$site_token}}",
            'image_url': "{{$image_url}}",
            'oauth_type': "{{$oauth_type}}",
            'status': "success",
            'message': "로그인이 성공하였습니다.",
        }
        window.addEventListener('message', (e) => {
            if (e.data.action === 'close') {
                window.close();
            }
        });
        window.opener.postMessage(userInfo, "*");
    </script>
</body>

</html>