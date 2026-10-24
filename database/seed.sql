-- Default platforms; safe to re-run. Platforms with historical clicks are hidden rather than deleted.

INSERT INTO platforms (slug, name, share_url_template, icon, color, sort_order, is_active) VALUES
    ('facebook', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u={url}',              'bi-facebook',      '#1877F2', 10, 1),
    ('x',        'X',        'https://x.com/intent/tweet?url={url}&text={title}',               'bi-twitter-x',     '#000000', 20, 1),
    ('whatsapp', 'WhatsApp', 'https://api.whatsapp.com/send?text={title}%20{url}',              'bi-whatsapp',      '#25D366', 30, 1),
    ('telegram', 'Telegram', 'https://t.me/share/url?url={url}&text={title}',                   'bi-telegram',      '#229ED9', 40, 1),
    ('email',    'Email',    'mailto:?subject={title}&body={description}%0A%0A{url}',           'bi-envelope-fill', '#5F6B7A', 50, 1)
ON DUPLICATE KEY UPDATE id = id;

-- Keep historical LinkedIn click rows intact while ensuring it is not shown as a share option.
UPDATE platforms SET is_active = 0 WHERE slug = 'linkedin';
