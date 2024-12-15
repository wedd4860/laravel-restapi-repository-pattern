<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>already</title>
</head>

<body>
    <script>
        window.addEventListener('message', (e) => {
            if (e.data.action === 'close') {
                window.close();
            }
        });
        window.opener.postMessage({
            'status': 'failed',
            'message': 'Account has already been registered as an email member.'
        }, '*');
    </script>
</body>