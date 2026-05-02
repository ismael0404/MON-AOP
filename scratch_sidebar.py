import os
import glob

base_dir = r"c:\xampp\htdocs\MON AOP"
roles = ['admin', 'medecin', 'patient', 'laborantin', 'caissier']

html_full = """
    <div class="nav-section-title">Communication & Finances</div>
    <a class="nav-item" href="../notifications/index.php">
      <span class="material-icons">notifications</span> Notifications
    </a>
    <a class="nav-item" href="../modules/messages/index.php">
      <span class="material-icons">chat</span> Messagerie
    </a>
    <a class="nav-item" href="../modules/paiements/index.php">
      <span class="material-icons">payments</span> Paiements
    </a>
"""

html_partial = """
    <div class="nav-section-title">Communication</div>
    <a class="nav-item" href="../notifications/index.php">
      <span class="material-icons">notifications</span> Notifications
    </a>
    <a class="nav-item" href="../modules/messages/index.php">
      <span class="material-icons">chat</span> Messagerie
    </a>
"""

count = 0
for role in roles:
    files = glob.glob(os.path.join(base_dir, role, '*.php'))
    for f in files:
        with open(f, 'r', encoding='utf-8') as file:
            content = file.read()
            
        if "Communication" in content:
            continue # already processed
        
        new_content = content
        if role in ['admin', 'patient', 'caissier']:
            if '<div class="nav-section-title">Système</div>' in content:
                new_content = content.replace('<div class="nav-section-title">Système</div>', html_full + '    <div class="nav-section-title">Système</div>')
            else:
                new_content = content.replace('</nav>', html_full + '  </nav>')
        else:
            new_content = content.replace('</nav>', html_partial + '  </nav>')
            
        if new_content != content:
            with open(f, 'w', encoding='utf-8') as file:
                file.write(new_content)
            count += 1

print(f"Updated {count} files.")
