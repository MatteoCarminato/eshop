module.exports = {
    apps: [
        {
            name: 'whatsapp-bot',
            script: './src/index.js',
            instances: 1,
            exec_mode: 'fork',
            env: {
                NODE_ENV: 'production',
                PORT: 3000,
            },
            autorestart: true,
            max_memory_restart: '500M',
            error_file: './logs/error.log',
            out_file: './logs/out.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
            watch: false,
        },
    ],
};