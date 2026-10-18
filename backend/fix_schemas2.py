import os
import glob

def replace_in_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    original = content
    
    # Forms
    content = content.replace(r'public static function configure(Schema $schema): Schema', r'public static function configure(\Filament\Forms\Form $form): \Filament\Forms\Form')
    content = content.replace(r'public function form(Schema $schema): Schema', r'public function form(\Filament\Forms\Form $form): \Filament\Forms\Form')

    # Specific fix for Infolists: if it's an infolist, replace Form $form with Infolist $infolist
    if 'Infolist' in filepath:
        content = content.replace(r'\Filament\Forms\Form $form', r'\Filament\Infolists\Infolist $infolist')
        content = content.replace(r'\Filament\Forms\Form', r'\Filament\Infolists\Infolist')
        content = content.replace(r'return $form', r'return $infolist')
        
        # Ensure 'use Filament\Forms\Form;' is replaced if it exists
        content = content.replace(r'use Filament\Forms\Form;', r'use Filament\Infolists\Infolist;')

    if original != content:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"Updated: {filepath}")

for root, dirs, files in os.walk('/home/boburkhan/Desktop/order-mini-app/backend/app/Filament'):
    for file in files:
        if file.endswith('.php'):
            replace_in_file(os.path.join(root, file))
