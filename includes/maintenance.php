<?php
/**
 * Maintenance Mode Template
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0a0a0f;
            --bg-card: #12121a;
            --neon-cyan: #00f0ff;
            --neon-magenta: #ff00aa;
            --text-primary: #ffffff;
            --text-secondary: #8a8a9a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Exo 2', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image:
                linear-gradient(rgba(0, 240, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 240, 255, 0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: 0;
        }

        .maintenance-container {
            position: relative;
            z-index: 1;
            max-width: 600px;
            padding: 60px 40px;
            background: var(--bg-card);
            border: 1px solid rgba(0, 240, 255, 0.2);
            border-radius: 12px;
            box-shadow: 0 0 60px rgba(0, 240, 255, 0.1);
        }

        .maintenance-icon {
            font-size: 4rem;
            margin-bottom: 24px;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-magenta));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }

        h1 {
            font-family: 'Orbitron', monospace;
            font-size: 2rem;
            margin-bottom: 16px;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-magenta));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.7;
        }

        .site-name {
            margin-top: 40px;
            font-family: 'Orbitron', monospace;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1>Under Maintenance</h1>
        <p><?= htmlspecialchars($maintenanceMessage ?? 'We are currently performing maintenance. Please check back soon.') ?></p>
        <div class="site-name"><?= SITE_NAME ?></div>
    </div>
</body>
</html>
