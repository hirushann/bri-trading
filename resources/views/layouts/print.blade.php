<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #1a56db; }
        .header p { margin: 5px 0; color: #666; }
        .billing-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .billing-info h3 { margin-top: 0; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
        table.items td { padding: 12px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; font-size: 1.1em; border-top: 2px solid #333; background: #fafafa; }
        .footer { margin-top: 50px; text-align: center; font-size: 0.9em; color: #888; border-top: 1px solid #eee; padding-top: 20px; }
        .signature { margin-top: 30px; margin-left: auto; width: 200px; text-align: center; border-top: 1px solid #333; padding-top: 5px; }
    </style>
</head>
<body>
    @yield('content')
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
